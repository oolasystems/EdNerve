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
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExaminationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'StrongPass@123';

    private Institution $institution;
    private int $adminRoleId;
    private int $facultyRoleId;
    private int $officerRoleId;
    private int $studentRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = Institution::create([
            'code' => 'EXMC',
            'name' => 'Examination Test College',
            'slug' => 'exmc',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        $this->adminRoleId = Role::create(['code' => 'institution_admin', 'name' => 'Institution Admin', 'status' => 'active'])->id;
        $this->facultyRoleId = Role::create(['code' => 'faculty', 'name' => 'Faculty', 'status' => 'active'])->id;
        $this->officerRoleId = Role::create(['code' => 'examination_officer', 'name' => 'Examination Officer', 'status' => 'active'])->id;
        $this->studentRoleId = Role::create(['code' => 'student', 'name' => 'Student', 'status' => 'active'])->id;
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

        DB::table('user_role_assignments')->insert([
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

    /** Confirm MFA for the user so privileged write routes become reachable. */
    private function confirmMfa(User $user): void
    {
        DB::table('user_mfa_secrets')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'secret' => 'KVKFQXREADAANFLM', // value irrelevant — gate checks confirmed_at only
                'confirmed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function seedAcademic(): array
    {
        $programme = Programme::create([
            'institution_id' => $this->institution->id,
            'code' => 'BTCSE',
            'name' => 'B.Tech CSE',
            'level' => 'UG',
            'duration_semesters' => 8,
            'status' => 'active',
        ]);

        $subject = Subject::create([
            'institution_id' => $this->institution->id,
            'code' => 'CS401',
            'name' => 'Compiler Design',
            'credits' => 4,
            'subject_type' => 'theory',
            'status' => 'active',
        ]);

        $studentUser = $this->makeUser('student.exmc.test', $this->studentRoleId);
        StudentProfile::create([
            'institution_id' => $this->institution->id,
            'user_id' => $studentUser->id,
            'programme_id' => $programme->id,
            'enrollment_status' => 'current',
        ]);

        return [$programme, $subject];
    }

    private function seedExamWithSubject(User $actor): array
    {
        $this->confirmMfa($actor);
        Sanctum::actingAs($actor);

        [$programme, $subject] = $this->seedAcademic();

        $examId = $this->postJson('/api/examinations', [
            'title' => 'End-Semester Examination — December 2026',
            'kind' => 'end_sem',
            'programme_id' => $programme->id,
            'semester' => 3,
            'section' => 'A',
        ])->assertCreated()->json('exam.id');

        $this->postJson("/api/examinations/{$examId}/subjects", [
            'subject_id' => $subject->id,
            'exam_date' => '2026-12-10',
            'start_time' => '10:00',
            'end_time' => '13:00',
            'venue' => 'FET-101',
            'pass_percent' => 40,
            'components' => [
                ['name' => 'Internal', 'max_marks' => 20],
                ['name' => 'Theory', 'max_marks' => 80],
            ],
        ])->assertCreated();

        return [$programme, $subject, $examId];
    }

    public function test_student_cannot_view_examinations(): void
    {
        $student = $this->makeUser('student.exmc.test', $this->studentRoleId);
        Sanctum::actingAs($student);
        $this->getJson('/api/examinations')->assertForbidden();
    }

    public function test_faculty_can_view_but_cannot_create_exam(): void
    {
        $faculty = $this->makeUser('faculty.exmc.test', $this->facultyRoleId);
        [$programme] = $this->seedAcademic();

        Sanctum::actingAs($faculty);
        $this->getJson('/api/examinations')->assertOk();
        $this->postJson('/api/examinations', [
            'title' => 'Mid-Semester',
            'kind' => 'mid_sem',
            'programme_id' => $programme->id,
            'semester' => 3,
        ])->assertForbidden();
    }

    public function test_exam_officer_can_schedule_and_publish(): void
    {
        $officer = $this->makeUser('officer.exmc.test', $this->officerRoleId);
        // Privileged roles must have MFA to call write routes.
        $this->confirmMfa($officer);
        $studentProfile = null;
        [$programme, $subject, $examId] = $this->seedExamWithSubject($officer);

        $studentProfile = StudentProfile::where('institution_id', $this->institution->id)->firstOrFail();

        $detail = $this->getJson("/api/examinations/{$examId}")->assertOk();
        $components = collect($detail->json('subjects.0.components'))->pluck('id', 'name');

        $this->putJson("/api/examinations/{$examId}/marks", [
            'subject_id' => $detail->json('subjects.0.id'),
            'component_id' => $components['Internal'],
            'records' => [
                ['student_profile_id' => $studentProfile->id, 'status' => 'present', 'marks_obtained' => 18],
            ],
        ])->assertOk()->assertJsonPath('saved', 1);

        $this->putJson("/api/examinations/{$examId}/marks", [
            'subject_id' => $detail->json('subjects.0.id'),
            'component_id' => $components['Theory'],
            'records' => [
                ['student_profile_id' => $studentProfile->id, 'status' => 'present', 'marks_obtained' => 72],
            ],
        ])->assertOk()->assertJsonPath('saved', 1);

        // Over-max rejected
        $this->putJson("/api/examinations/{$examId}/marks", [
            'subject_id' => $detail->json('subjects.0.id'),
            'component_id' => $components['Theory'],
            'records' => [
                ['student_profile_id' => $studentProfile->id, 'status' => 'present', 'marks_obtained' => 95],
            ],
        ])->assertStatus(422);

        $results = $this->getJson("/api/examinations/{$examId}/results")->assertOk();
        $this->assertEquals(90.0, $results->json('results.0.total.percent'));
        $this->assertEquals('O', $results->json('results.0.total.grade'));
        $this->assertEquals('pass', $results->json('results.0.total.status'));

        // Separation of duties: the officer who entered these marks cannot publish them.
        $this->postJson("/api/examinations/{$examId}/publish")->assertForbidden();

        // Another privileged user (institution admin) can.
        $admin = $this->makeUser('admin.exmc.test', $this->adminRoleId);
        $this->confirmMfa($admin);
        Sanctum::actingAs($admin);
        $this->postJson("/api/examinations/{$examId}/publish")->assertOk();

        // Frozen after publish
        $this->putJson("/api/examinations/{$examId}/marks", [
            'subject_id' => $detail->json('subjects.0.id'),
            'component_id' => $components['Internal'],
            'records' => [
                ['student_profile_id' => $studentProfile->id, 'status' => 'present', 'marks_obtained' => 10],
            ],
        ])->assertStatus(409);
    }

    public function test_invigilation_assign_and_my_duties(): void
    {
        $officer = $this->makeUser('officer.exmc.test', $this->officerRoleId);
        $faculty = $this->makeUser('faculty.exmc.test', $this->facultyRoleId);
        [$programme, $subject, $examId] = $this->seedExamWithSubject($officer);

        $detail = $this->getJson("/api/examinations/{$examId}")->assertOk();
        $subjectEntryId = $detail->json('subjects.0.id');

        $this->postJson("/api/examinations/{$examId}/subjects/{$subjectEntryId}/invigilations", [
            'user_id' => $faculty->id,
            'duty' => 'chief',
        ])->assertCreated();

        Sanctum::actingAs($faculty);
        $this->getJson('/api/examinations/invigilation/mine')
            ->assertOk()
            ->assertJsonPath('duties.0.duty', 'chief');
    }


    public function test_revaluation_flow(): void
    {
        $officer = $this->makeUser('officer.exmc.test', $this->officerRoleId);
        [$programme, $subject, $examId] = $this->seedExamWithSubject($officer);

        $studentProfile = StudentProfile::where(
            'institution_id',
            $this->institution->id
        )->firstOrFail();

        $created = $this->postJson('/api/examinations/revaluations', [
            'exam_id' => $examId,
            'student_profile_id' => $studentProfile->id,
            'subject_id' => $subject->id,
            'reason' => 'Suspected totaling error in Theory component.',
            'fee_amount' => 100,
        ])->assertCreated();

        $revaluationId = $created->json('revaluation.id');

        // Duplicate blocked
        $this->postJson('/api/examinations/revaluations', [
            'exam_id' => $examId,
            'student_profile_id' => $studentProfile->id,
            'subject_id' => $subject->id,
            'reason' => 'Duplicate request for the same subject.',
        ])->assertStatus(422);

        // Revaluation status lifecycle
        $this->patchJson(
            "/api/examinations/revaluations/{$revaluationId}/status",
            ['status' => 'in_review']
        )->assertOk();

        $this->patchJson(
            "/api/examinations/revaluations/{$revaluationId}/status",
            ['status' => 'completed']
        )->assertOk();
    }

    public function test_hall_ticket_payload(): void
    {
        $officer = $this->makeUser('officer.exmc.test', $this->officerRoleId);
        [$programme, $subject, $examId] = $this->seedExamWithSubject($officer);

        $studentProfile = StudentProfile::where('institution_id', $this->institution->id)->firstOrFail();

        $ticket = $this->getJson("/api/examinations/{$examId}/hall-ticket?student_profile_id=" . $studentProfile->id)
            ->assertOk()
            ->assertJsonPath('institution.code', 'EXMC')
            ->assertJsonPath('student.application_number', $studentProfile->application_number);

        $this->assertStringContainsString('EXMC', $ticket->json('hall_ticket_number'));
    }
}
