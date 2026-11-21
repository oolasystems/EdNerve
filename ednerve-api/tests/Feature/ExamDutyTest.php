<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamDutyAssignment;
use App\Models\ExamDutyRole;
use App\Models\ExamSubject;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExamDutyTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'StrongPass@123';
    private Institution $institution;
    private int $adminRoleId;
    private int $facultyRoleId;
    private int $officerRoleId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->institution = Institution::create([
            'code' => 'DUTY', 'name' => 'Duty Test College', 'slug' => 'duty',
            'institution_type' => 'university', 'country' => 'India', 'status' => 'active',
        ]);
        $this->adminRoleId = Role::create(['code' => 'institution_admin', 'name' => 'Institution Admin', 'status' => 'active'])->id;
        $this->facultyRoleId = Role::create(['code' => 'faculty', 'name' => 'Faculty', 'status' => 'active'])->id;
        $this->officerRoleId = Role::create(['code' => 'examination_officer', 'name' => 'Examination Officer', 'status' => 'active'])->id;
    }

    private function makeUser(string $email, int $roleId): User
    {
        $user = User::create([
            'account_type' => 'institution', 'institution_id' => $this->institution->id,
            'name' => $email, 'email' => $email, 'password' => Hash::make(self::PASSWORD), 'status' => 'active',
        ]);
        \DB::table('user_role_assignments')->insert([
            'institution_id' => $this->institution->id, 'user_id' => $user->id, 'role_id' => $roleId,
            'academic_unit_id' => null, 'is_primary' => true, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return $user;
    }

    private function confirmMfa(User $user): void
    {
        \DB::table('user_mfa_secrets')->updateOrInsert(
            ['user_id' => $user->id],
            ['secret' => 'KVKFQXREADAANFLM', 'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now()]
        );
    }

    private function seedExam(User $actor): array
    {
        $this->confirmMfa($actor);
        Sanctum::actingAs($actor);
        $programme = Programme::create(['institution_id' => $this->institution->id, 'code' => 'BTCSE', 'name' => 'B.Tech CSE', 'level' => 'UG', 'duration_semesters' => 8, 'status' => 'active']);
        $subject = Subject::create(['institution_id' => $this->institution->id, 'code' => 'CS401', 'name' => 'Compiler Design', 'credits' => 4, 'subject_type' => 'theory', 'status' => 'active']);
        $examId = $this->postJson('/api/examinations', ['title' => 'End Sem', 'kind' => 'end_sem', 'programme_id' => $programme->id, 'semester' => 7, 'section' => 'A'])->assertCreated()->json('exam.id');
        $this->postJson("/api/examinations/{$examId}/subjects", [
            'subject_id' => $subject->id, 'exam_date' => '2026-12-10', 'start_time' => '10:00', 'end_time' => '13:00', 'venue' => 'H1', 'pass_percent' => 40,
            'components' => [['name' => 'Theory', 'max_marks' => 100]],
        ])->assertCreated();
        $entry = ExamSubject::where('exam_id', $examId)->firstOrFail();
        return [$examId, $entry->id, $programme, $subject];
    }

    public function test_duty_roles_seeded_and_listable(): void
    {
        $officer = $this->makeUser('officer@duty.test', $this->officerRoleId);
        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        Sanctum::actingAs($officer);
        $res = $this->getJson('/api/examinations/duty-roles')->assertOk();
        $this->assertGreaterThanOrEqual(8, count($res->json('roles')));
        $this->assertNotNull(collect($res->json('roles'))->firstWhere('code', 'INVIGILATOR'));
    }

    public function test_assign_duty_creates_assignment_and_legacy_sync(): void
    {
        $admin = $this->makeUser('admin@duty.test', $this->adminRoleId);
        $faculty = $this->makeUser('faculty@duty.test', $this->facultyRoleId);
        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId, $entryId] = $this->seedExam($admin);
        $roleId = ExamDutyRole::where('institution_id', $this->institution->id)->where('code', 'CHIEF_SUPERINTENDENT')->value('id');

        $this->postJson("/api/examinations/{$examId}/duty-assignments", [
            'exam_subject_id' => $entryId,
            'user_id' => $faculty->id,
            'duty_role_id' => $roleId,
        ])->assertCreated()->assertJsonPath('assignment.status', 'assigned');

        $this->assertDatabaseHas('exam_duty_assignments', [
            'exam_subject_id' => $entryId, 'user_id' => $faculty->id, 'duty_role_id' => $roleId, 'status' => 'assigned',
        ]);
        $this->assertDatabaseHas('exam_invigilations', [
            'exam_subject_id' => $entryId, 'user_id' => $faculty->id, 'duty' => 'chief',
        ]);

        $roster = $this->getJson("/api/examinations/{$examId}/duty-roster")->assertOk();
        $this->assertEquals($faculty->id, $roster->json('slots.0.assignments.0.user.id'));
        $this->assertEquals('CHIEF_SUPERINTENDENT', $roster->json('slots.0.assignments.0.duty_role.code'));
    }

    public function test_auto_assign_respects_availability_and_balances(): void
    {
        $admin = $this->makeUser('admin@duty.test', $this->adminRoleId);
        $f1 = $this->makeUser('f1@duty.test', $this->facultyRoleId);
        $f2 = $this->makeUser('f2@duty.test', $this->facultyRoleId);
        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId, $entryId] = $this->seedExam($admin);

        // Mark f1 unavailable on exam date -> auto-assign should pick f2
        $this->postJson("/api/examinations/{$examId}/duty-availability", [
            'user_id' => $f1->id, 'duty_date' => '2026-12-10', 'available' => false, 'reason' => 'Leave',
        ])->assertCreated();

        $invRole = ExamDutyRole::where('institution_id', $this->institution->id)->where('code', 'INVIGILATOR')->value('id');
        $res = $this->postJson("/api/examinations/{$examId}/duty-roster/auto-assign", [
            'duty_role_id' => $invRole, 'required_per_slot' => 1, 'staff_user_ids' => [$f1->id, $f2->id],
        ])->assertOk();
        $this->assertEquals(1, $res->json('created'));

        $this->assertDatabaseHas('exam_duty_assignments', ['exam_subject_id' => $entryId, 'user_id' => $f2->id]);
        $this->assertDatabaseMissing('exam_duty_assignments', ['exam_subject_id' => $entryId, 'user_id' => $f1->id]);
    }
}
