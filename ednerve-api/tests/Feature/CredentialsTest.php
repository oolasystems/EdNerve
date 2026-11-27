<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Programme;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CredentialsTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'StrongPass@123';

    private Institution $institution;
    private int $adminRoleId;
    private int $officerRoleId;
    private int $facultyRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = Institution::create([
            'code' => 'CRSC',
            'name' => 'Credentials Test College',
            'slug' => 'crsc',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        $this->adminRoleId = Role::create(['code' => 'institution_admin', 'name' => 'Institution Admin', 'status' => 'active'])->id;
        $this->officerRoleId = Role::create(['code' => 'examination_officer', 'name' => 'Examination Officer', 'status' => 'active'])->id;
        $this->facultyRoleId = Role::create(['code' => 'faculty', 'name' => 'Faculty', 'status' => 'active'])->id;
    }

    private function makeUser(string $email, int $roleId): User
    {
        $user = User::create([
            'account_type' => 'institution',
            'institution_id' => $this->institution->id,
            'name' => $email,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'status' => 'active',
        ]);

        \DB::table('user_role_assignments')->insert([
            'institution_id' => $this->institution->id,
            'user_id' => $user->id,
            'role_id' => $roleId,
            'academic_unit_id' => null,
            'is_primary' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    /** Confirm MFA for the user (publish endpoint requires it for privileged roles). */
    private function confirmMfa(User $user): void
    {
        \DB::table('user_mfa_secrets')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'secret' => \Illuminate\Support\Facades\Crypt::encryptString('JBSWY3DPEHPK3PXP'),
                'confirmed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function seedExamAndStudent(User $actor, ?User $marker = null): array
    {
        $marker ??= $actor;
        $this->confirmMfa($marker);
        $this->confirmMfa($actor);

        // Marker enters marks; publisher publishes — SoD requires two people.
        Sanctum::actingAs($marker);

        $programmeModel = Programme::create([
            'institution_id' => $this->institution->id,
            'code' => 'BTCSE',
            'name' => 'B.Tech CSE',
            'level' => 'UG',
            'duration_semesters' => 8,
            'status' => 'active',
        ]);

        $subjectModel = Subject::create([
            'institution_id' => $this->institution->id,
            'code' => 'CS401',
            'name' => 'Compiler Design',
            'credits' => 4,
            'subject_type' => 'theory',
            'status' => 'active',
        ]);

        $studentRoleId = Role::firstOrCreate(['code' => 'student'], ['name' => 'Student', 'status' => 'active'])->id;
        $studentUser = $this->makeUser('student.crsc.test', $studentRoleId);
        StudentProfile::create([
            'institution_id' => $this->institution->id,
            'user_id' => $studentUser->id,
            'programme_id' => $programmeModel->id,
            'enrollment_status' => 'current',
        ]);

        $examId = $this->postJson('/api/examinations', [
            'title' => 'Credentials Test Exam',
            'kind' => 'end_sem',
            'programme_id' => $programmeModel->id,
            'semester' => 3,
        ])->assertCreated()->json('exam.id');

        $this->postJson("/api/examinations/{$examId}/subjects", [
            'subject_id' => $subjectModel->id,
            'exam_date' => '2026-12-15',
            'components' => [['name' => 'Theory', 'max_marks' => 100]],
        ])->assertCreated();

        $detail = $this->getJson("/api/examinations/{$examId}")->assertOk();
        $componentId = $detail->json('subjects.0.components.0.id');
        $studentProfile = StudentProfile::where('institution_id', $this->institution->id)->firstOrFail();

        $this->putJson("/api/examinations/{$examId}/marks", [
            'subject_id' => $detail->json('subjects.0.id'),
            'component_id' => $componentId,
            'records' => [['student_profile_id' => $studentProfile->id, 'status' => 'present', 'marks_obtained' => 88]],
        ])->assertOk();

        // Publisher takes over
        Sanctum::actingAs($actor);
        $this->postJson("/api/examinations/{$examId}/publish")->assertOk();

        return [$programmeModel, $subjectModel, $examId, $studentProfile];
    }

    public function test_admin_can_issue_and_verify_marksheet_credential(): void
    {
        $admin = $this->makeUser('admin.crsc.test', $this->adminRoleId);
        $officer = $this->makeUser('officer.crsc.test', $this->officerRoleId);
        [$programme, $subject, $examId, $studentProfile] = $this->seedExamAndStudent($admin, $officer);

        $issued = $this->postJson("/api/examinations/{$examId}/credentials/issue")->assertCreated();
        $this->assertSame(1, $issued->json('count'));

        $list = $this->getJson('/api/credentials')->assertOk()->json('data');
        $this->assertCount(1, $list);
        $number = $list[0]['credential_number'];
        $this->assertStringStartsWith('CRSC-', $number);

        // Public verification (no auth)
        $verify = $this->getJson("/api/verify/credentials/{$number}")->assertOk();

        $this->assertTrue($verify->json('valid'));
        $this->assertTrue($verify->json('signature_valid'));
        $this->assertSame('pass', $verify->json('results.overallStatus'));
    }

    public function test_tampered_credential_fails_verification(): void
    {
        $admin = $this->makeUser('admin.crsc.test', $this->adminRoleId);
        $officer = $this->makeUser('officer.crsc.test', $this->officerRoleId);
        [$programme, $subject, $examId, $studentProfile] = $this->seedExamAndStudent($admin, $officer);

        $this->postJson("/api/examinations/{$examId}/credentials/issue")->assertCreated();

        $credential = \DB::table('credentials')->first();
        \DB::table('credentials')->where('id', $credential->id)->update([
            'payload' => json_encode(array_replace(json_decode($credential->payload, true), ['tampered' => true])),
        ]);

        $number = $credential->credential_number;
        $verify = $this->getJson("/api/verify/credentials/{$number}")->assertOk();
        $this->assertFalse($verify->json('valid'));
        $this->assertSame('tampered', $verify->json('status'));
        $verify->assertJsonMissingPath('student')
            ->assertJsonMissingPath('results');
    }

    public function test_revoked_credential_shows_revoked_status(): void
    {
        $admin = $this->makeUser('admin.crsc.test', $this->adminRoleId);
        $officer = $this->makeUser('officer.crsc.test', $this->officerRoleId);
        [$programme, $subject, $examId, $studentProfile] = $this->seedExamAndStudent($admin, $officer);

        $this->postJson("/api/examinations/{$examId}/credentials/issue")->assertCreated();
        $credential = \App\Models\Credential::first();

        $this->patchJson("/api/credentials/{$credential->id}/revoke", ['reason' => 'Clerical error — reissuing'])
            ->assertOk();

        $verify = $this->getJson("/api/verify/credentials/{$credential->credential_number}")->assertOk();
        $this->assertSame('revoked', $verify->json('status'));
        $this->assertFalse($verify->json('valid'));
    }

    public function test_unknown_credential_returns_not_found(): void
    {
        $this->getJson('/api/verify/credentials/CRSC-2026-999999')->assertNotFound()
            ->assertJsonPath('status', 'not_found');
    }

    public function test_student_cannot_issue_credentials(): void
    {
        $faculty = $this->makeUser('faculty.crsc.test', $this->facultyRoleId);
        $admin = $this->makeUser('admin.crsc.test', $this->adminRoleId);
        $officer = $this->makeUser('officer.crsc.test', $this->officerRoleId);
        [$programme, $subject, $examId, $studentProfile] = $this->seedExamAndStudent($admin, $officer);

        Sanctum::actingAs($faculty);
        $this->postJson("/api/examinations/{$examId}/credentials/issue")->assertForbidden();
    }
}
