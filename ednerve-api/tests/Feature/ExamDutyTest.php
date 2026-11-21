<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\Exam;
use Illuminate\Support\Facades\DB;
use App\Models\ExamDutyAssignment;
use App\Models\ExamDutyRole;
use App\Models\ExamSubject;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use App\Services\AuditService;
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
            'code' => 'DUTY',
            'name' => 'Duty Test College',
            'slug' => 'duty',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);
        $this->adminRoleId = Role::create(['code' => 'institution_admin', 'name' => 'Institution Admin', 'status' => 'active'])->id;
        $this->facultyRoleId = Role::create(['code' => 'faculty', 'name' => 'Faculty', 'status' => 'active'])->id;
        $this->officerRoleId = Role::create(['code' => 'examination_officer', 'name' => 'Examination Officer', 'status' => 'active'])->id;
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

    private function confirmMfa(User $user): void
    {
        DB::table('user_mfa_secrets')->updateOrInsert(
            ['user_id' => $user->id],
            ['secret' => 'KVKFQXREADAANFLM', 'confirmed_at' => now(), 'created_at' => now(), 'updated_at' => now()]
        );
    }

    private function createAcademicSession(string $code): AcademicSession
    {
        return AcademicSession::create([
            'institution_id' => $this->institution->id,
            'code' => $code,
            'name' => "Academic Session {$code}",
            'start_date' => '2026-06-01',
            'end_date' => '2027-05-31',
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    private function createRelatedExam(
        Programme $programme,
        Subject $subject,
        ?int $academicSessionId,
        string $status,
        string $title
    ): array {
        $exam = Exam::create([
            'institution_id' => $this->institution->id,
            'title' => $title,
            'kind' => 'end_sem',
            'academic_session_id' => $academicSessionId,
            'programme_id' => $programme->id,
            'semester' => 7,
            'section' => 'A',
            'status' => $status,
        ]);

        $examSubject = ExamSubject::create([
            'institution_id' => $this->institution->id,
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'exam_date' => '2026-12-15',
            'start_time' => '10:00',
            'end_time' => '13:00',
            'venue' => 'H2',
            'pass_percent' => 40,
        ]);

        return [$exam, $examSubject];
    }

    private function createDutyAssignment(
        int $examSubjectId,
        User $staff,
        int $roleId,
        User $assignedBy,
        string $status = 'assigned'
    ): ExamDutyAssignment {
        return ExamDutyAssignment::create([
            'institution_id' => $this->institution->id,
            'exam_subject_id' => $examSubjectId,
            'user_id' => $staff->id,
            'duty_role_id' => $roleId,
            'status' => $status,
            'assigned_by' => $assignedBy->id,
            'assigned_at' => now(),
        ]);
    }

    private function seedExam(User $actor): array
    {
        $this->confirmMfa($actor);
        Sanctum::actingAs($actor);
        $programme = Programme::create(['institution_id' => $this->institution->id, 'code' => 'BTCSE', 'name' => 'B.Tech CSE', 'level' => 'UG', 'duration_semesters' => 8, 'status' => 'active']);
        $subject = Subject::create(['institution_id' => $this->institution->id, 'code' => 'CS401', 'name' => 'Compiler Design', 'credits' => 4, 'subject_type' => 'theory', 'status' => 'active']);
        $examId = $this->postJson('/api/examinations', ['title' => 'End Sem', 'kind' => 'end_sem', 'programme_id' => $programme->id, 'semester' => 7, 'section' => 'A'])->assertCreated()->json('exam.id');
        $this->postJson("/api/examinations/{$examId}/subjects", [
            'subject_id' => $subject->id,
            'exam_date' => '2026-12-10',
            'start_time' => '10:00',
            'end_time' => '13:00',
            'venue' => 'H1',
            'pass_percent' => 40,
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
            'exam_subject_id' => $entryId,
            'user_id' => $faculty->id,
            'duty_role_id' => $roleId,
            'status' => 'assigned',
        ]);
        $this->assertDatabaseHas('exam_invigilations', [
            'exam_subject_id' => $entryId,
            'user_id' => $faculty->id,
            'duty' => 'chief',
        ]);

        $roster = $this->getJson("/api/examinations/{$examId}/duty-roster")->assertOk();
        $this->assertEquals($faculty->id, $roster->json('slots.0.assignments.0.user.id'));
        $this->assertEquals('CHIEF_SUPERINTENDENT', $roster->json('slots.0.assignments.0.duty_role.code'));
    }

    public function test_new_assignment_is_rejected_after_roster_submission_without_legacy_sync(): void
    {
        $admin = $this->makeUser('admin-roster-lock@duty.test', $this->adminRoleId);
        $faculty1 = $this->makeUser('faculty-existing@duty.test', $this->facultyRoleId);
        $faculty2 = $this->makeUser('faculty-new@duty.test', $this->facultyRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId, $entryId] = $this->seedExam($admin);

        $roleId = ExamDutyRole::where('institution_id', $this->institution->id)
            ->where('code', 'INVIGILATOR')
            ->value('id');

        // Create an assignment while the roster is still in draft.
        $existingResponse = $this->postJson(
            "/api/examinations/{$examId}/duty-assignments",
            [
                'exam_subject_id' => $entryId,
                'user_id' => $faculty1->id,
                'duty_role_id' => $roleId,
            ]
        )->assertCreated();

        $existingAssignmentId = $existingResponse->json('assignment.id');

        // Submit the roster. It must no longer accept structural changes.
        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/submit"
        )->assertOk()
            ->assertJsonPath('roster.status', 'submitted');

        // Attempt to add another staff member after submission.
        $this->postJson(
            "/api/examinations/{$examId}/duty-assignments",
            [
                'exam_subject_id' => $entryId,
                'user_id' => $faculty2->id,
                'duty_role_id' => $roleId,
            ]
        )->assertStatus(409);

        // No additional assignment must have been created.
        $this->assertDatabaseCount('exam_duty_assignments', 1);

        // The original assignment must remain unchanged.
        $this->assertDatabaseHas('exam_duty_assignments', [
            'id' => $existingAssignmentId,
            'exam_subject_id' => $entryId,
            'user_id' => $faculty1->id,
            'status' => 'assigned',
        ]);

        // Legacy invigilation entry for the original assignment remains.
        $this->assertDatabaseHas('exam_invigilations', [
            'institution_id' => $this->institution->id,
            'exam_subject_id' => $entryId,
            'user_id' => $faculty1->id,
            'duty' => 'invigilator',
        ]);

        // No legacy invigilation entry must be created for the rejected request.
        $this->assertDatabaseMissing('exam_invigilations', [
            'institution_id' => $this->institution->id,
            'exam_subject_id' => $entryId,
            'user_id' => $faculty2->id,
        ]);
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
            'user_id' => $f1->id,
            'duty_date' => '2026-12-10',
            'available' => false,
            'reason' => 'Leave',
        ])->assertCreated();

        $invRole = ExamDutyRole::where('institution_id', $this->institution->id)->where('code', 'INVIGILATOR')->value('id');
        $res = $this->postJson("/api/examinations/{$examId}/duty-roster/auto-assign", [
            'duty_role_id' => $invRole,
            'required_per_slot' => 1,
            'staff_user_ids' => [$f1->id, $f2->id],
        ])->assertOk();
        $this->assertEquals(1, $res->json('created'));

        $this->assertDatabaseHas('exam_duty_assignments', ['exam_subject_id' => $entryId, 'user_id' => $f2->id]);
        $this->assertDatabaseMissing('exam_duty_assignments', ['exam_subject_id' => $entryId, 'user_id' => $f1->id]);
    }


    public function test_auto_assign_replaces_declined_assignment_without_overwriting_history(): void
    {
        $admin = $this->makeUser('admin-replacement@duty.test', $this->adminRoleId);
        $faculty1 = $this->makeUser('faculty1-replacement@duty.test', $this->facultyRoleId);
        $faculty2 = $this->makeUser('faculty2-replacement@duty.test', $this->facultyRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);

        [$examId, $entryId] = $this->seedExam($admin);

        $roleId = ExamDutyRole::where('institution_id', $this->institution->id)
            ->where('code', 'INVIGILATOR')
            ->value('id');

        // Initially assign the duty to faculty1.
        $assignmentResponse = $this->postJson(
            "/api/examinations/{$examId}/duty-assignments",
            [
                'exam_subject_id' => $entryId,
                'user_id' => $faculty1->id,
                'duty_role_id' => $roleId,
            ]
        )->assertCreated();

        $originalAssignmentId = $assignmentResponse->json('assignment.id');

        // Faculty1 declines the assignment.
        Sanctum::actingAs($faculty1);

        $this->postJson(
            "/api/examinations/{$examId}/duty-assignments/{$originalAssignmentId}/respond",
            [
                'response' => 'declined',
                'remarks' => 'Unable to attend.',
            ]
        )->assertOk();

        $originalAssignment = ExamDutyAssignment::findOrFail($originalAssignmentId);

        $this->assertEquals('declined', $originalAssignment->status);
        $this->assertNotNull($originalAssignment->responded_at);

        $originalRespondedAt = $originalAssignment->responded_at;

        // Examination officer/admin runs auto-assignment with replacement enabled.
        Sanctum::actingAs($admin);

        $response = $this->postJson(
            "/api/examinations/{$examId}/duty-roster/auto-assign",
            [
                'duty_role_id' => $roleId,
                'required_per_slot' => 1,
                'staff_user_ids' => [$faculty1->id, $faculty2->id],
                'replace_declined' => true,
            ]
        )->assertOk();

        $this->assertEquals(1, $response->json('created'));

        // Original assignment must remain as history and point to its replacement.
        $originalAssignment->refresh();

        $this->assertEquals('replaced', $originalAssignment->status);
        $this->assertNotNull($originalAssignment->replaced_by_assignment_id);
        $this->assertEquals($originalRespondedAt->toDateTimeString(), $originalAssignment->responded_at->toDateTimeString());

        // Replacement must be a separate assignment for faculty2.
        $replacement = ExamDutyAssignment::findOrFail(
            $originalAssignment->replaced_by_assignment_id
        );

        $this->assertNotEquals($originalAssignment->id, $replacement->id);
        $this->assertEquals($faculty2->id, $replacement->user_id);
        $this->assertEquals($entryId, $replacement->exam_subject_id);
        $this->assertEquals('assigned', $replacement->status);
        $this->assertEquals($roleId, $replacement->duty_role_id);

        // Both records must exist.
        $this->assertDatabaseCount('exam_duty_assignments', 2);

        // Legacy invigilation must now contain the replacement staff member.
        $this->assertDatabaseHas('exam_invigilations', [
            'institution_id' => $this->institution->id,
            'exam_subject_id' => $entryId,
            'user_id' => $faculty2->id,
            'duty' => 'invigilator',
        ]);

        $this->assertDatabaseMissing('exam_invigilations', [
            'institution_id' => $this->institution->id,
            'exam_subject_id' => $entryId,
            'user_id' => $faculty1->id,
        ]);
    }

    public function test_staff_can_accept_and_decline_their_own_assignments(): void
    {
        $admin = $this->makeUser('admin-response@duty.test', $this->adminRoleId);
        $faculty1 = $this->makeUser('faculty-accept@duty.test', $this->facultyRoleId);
        $faculty2 = $this->makeUser('faculty-decline@duty.test', $this->facultyRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId, $entryId] = $this->seedExam($admin);

        $roleId = ExamDutyRole::where('institution_id', $this->institution->id)
            ->where('code', 'INVIGILATOR')
            ->value('id');

        $assignment1 = $this->postJson(
            "/api/examinations/{$examId}/duty-assignments",
            [
                'exam_subject_id' => $entryId,
                'user_id' => $faculty1->id,
                'duty_role_id' => $roleId,
            ]
        )->assertCreated()->json('assignment.id');

        $assignment2 = $this->postJson(
            "/api/examinations/{$examId}/duty-assignments",
            [
                'exam_subject_id' => $entryId,
                'user_id' => $faculty2->id,
                'duty_role_id' => $roleId,
            ]
        )->assertCreated()->json('assignment.id');

        $this->confirmMfa($faculty1);
        $this->confirmMfa($faculty2);

        // Faculty1 accepts their own assignment.
        Sanctum::actingAs($faculty1);

        $this->postJson(
            "/api/examinations/{$examId}/duty-assignments/{$assignment1}/respond",
            ['response' => 'accepted', 'remarks' => 'I will attend.']
        )->assertOk()
            ->assertJsonPath('assignment.status', 'accepted');

        $this->assertDatabaseHas('exam_duty_assignments', [
            'id' => $assignment1,
            'user_id' => $faculty1->id,
            'status' => 'accepted',
        ]);

        $this->assertNotNull(
            ExamDutyAssignment::findOrFail($assignment1)->responded_at
        );

        // Faculty2 declines their own assignment.
        Sanctum::actingAs($faculty2);

        $this->postJson(
            "/api/examinations/{$examId}/duty-assignments/{$assignment2}/respond",
            ['response' => 'declined', 'remarks' => 'I am unavailable.']
        )->assertOk()
            ->assertJsonPath('assignment.status', 'declined');

        $this->assertDatabaseHas('exam_duty_assignments', [
            'id' => $assignment2,
            'user_id' => $faculty2->id,
            'status' => 'declined',
            'remarks' => 'I am unavailable.',
        ]);

        $this->assertNotNull(
            ExamDutyAssignment::findOrFail($assignment2)->responded_at
        );
    }

    public function test_staff_cannot_respond_to_another_staff_members_assignment_or_respond_twice(): void
    {
        $admin = $this->makeUser('admin-security@duty.test', $this->adminRoleId);
        $faculty1 = $this->makeUser('faculty-owner@duty.test', $this->facultyRoleId);
        $faculty2 = $this->makeUser('faculty-other@duty.test', $this->facultyRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId, $entryId] = $this->seedExam($admin);

        $roleId = ExamDutyRole::where('institution_id', $this->institution->id)
            ->where('code', 'INVIGILATOR')
            ->value('id');

        $assignmentId = $this->postJson(
            "/api/examinations/{$examId}/duty-assignments",
            [
                'exam_subject_id' => $entryId,
                'user_id' => $faculty1->id,
                'duty_role_id' => $roleId,
            ]
        )->assertCreated()->json('assignment.id');

        $this->confirmMfa($faculty1);
        $this->confirmMfa($faculty2);

        // Another staff member must not respond to this assignment.
        Sanctum::actingAs($faculty2);

        $this->postJson(
            "/api/examinations/{$examId}/duty-assignments/{$assignmentId}/respond",
            ['response' => 'accepted']
        )->assertNotFound();

        // The actual assignee can respond once.
        Sanctum::actingAs($faculty1);

        $this->postJson(
            "/api/examinations/{$examId}/duty-assignments/{$assignmentId}/respond",
            ['response' => 'accepted']
        )->assertOk();

        // A second response must be rejected.
        $this->postJson(
            "/api/examinations/{$examId}/duty-assignments/{$assignmentId}/respond",
            ['response' => 'declined']
        )->assertStatus(409);

        $this->assertDatabaseHas('exam_duty_assignments', [
            'id' => $assignmentId,
            'user_id' => $faculty1->id,
            'status' => 'accepted',
        ]);
    }

    public function test_manager_cannot_set_assignment_response_status_directly(): void
    {
        $admin = $this->makeUser('admin-status@duty.test', $this->adminRoleId);
        $faculty = $this->makeUser('faculty-status@duty.test', $this->facultyRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId, $entryId] = $this->seedExam($admin);

        $roleId = ExamDutyRole::where('institution_id', $this->institution->id)
            ->where('code', 'INVIGILATOR')
            ->value('id');

        // Manager must not create an assignment already marked accepted.
        $this->postJson(
            "/api/examinations/{$examId}/duty-assignments",
            [
                'exam_subject_id' => $entryId,
                'user_id' => $faculty->id,
                'duty_role_id' => $roleId,
                'status' => 'accepted',
            ]
        )->assertUnprocessable();

        // Create a normal pending assignment.
        $assignmentId = $this->postJson(
            "/api/examinations/{$examId}/duty-assignments",
            [
                'exam_subject_id' => $entryId,
                'user_id' => $faculty->id,
                'duty_role_id' => $roleId,
            ]
        )->assertCreated()->json('assignment.id');

        // Manager must not change its status directly.
        $this->patchJson(
            "/api/examinations/{$examId}/duty-assignments/{$assignmentId}",
            ['status' => 'accepted']
        )->assertUnprocessable();

        $this->assertDatabaseHas('exam_duty_assignments', [
            'id' => $assignmentId,
            'status' => 'assigned',
        ]);
    }


    public function test_auto_assign_counts_scheduled_and_published_exams_in_same_academic_session(): void
    {
        $admin = $this->makeUser('admin-session-load@duty.test', $this->adminRoleId);
        $loadedStaff = $this->makeUser('a-loaded@duty.test', $this->facultyRoleId);
        $freeStaff = $this->makeUser('b-free@duty.test', $this->facultyRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId, $targetSubjectId, $programme, $subject] = $this->seedExam($admin);

        $academicSession = $this->createAcademicSession('2026-27');

        $targetExam = Exam::findOrFail($examId);
        $targetExam->academic_session_id = $academicSession->id;
        $targetExam->save();

        [, $scheduledSubject] = $this->createRelatedExam(
            $programme,
            $subject,
            $academicSession->id,
            'scheduled',
            'Scheduled Examination'
        );

        [, $publishedSubject] = $this->createRelatedExam(
            $programme,
            $subject,
            $academicSession->id,
            'published',
            'Published Examination'
        );

        $roleId = ExamDutyRole::where('institution_id', $this->institution->id)
            ->where('code', 'INVIGILATOR')
            ->value('id');

        // Existing workload from both eligible examinations.
        $this->createDutyAssignment($scheduledSubject->id, $loadedStaff, $roleId, $admin);
        $this->createDutyAssignment($publishedSubject->id, $loadedStaff, $roleId, $admin);

        $response = $this->postJson(
            "/api/examinations/{$examId}/duty-roster/auto-assign",
            [
                'duty_role_id' => $roleId,
                'required_per_slot' => 1,
                'staff_user_ids' => [$loadedStaff->id, $freeStaff->id],
            ]
        )->assertOk();

        $this->assertEquals(1, $response->json('created'));

        // The free staff member must be selected despite having a higher user ID.
        $this->assertDatabaseHas('exam_duty_assignments', [
            'exam_subject_id' => $targetSubjectId,
            'user_id' => $freeStaff->id,
            'status' => 'assigned',
        ]);

        $this->assertDatabaseMissing('exam_duty_assignments', [
            'exam_subject_id' => $targetSubjectId,
            'user_id' => $loadedStaff->id,
        ]);
    }

    public function test_structural_assignment_operations_are_rejected_after_roster_publication(): void
    {
        $admin = $this->makeUser('admin-immutable@duty.test', $this->adminRoleId);
        $officer = $this->makeUser('officer-immutable@duty.test', $this->officerRoleId);
        $faculty1 = $this->makeUser('faculty-immutable1@duty.test', $this->facultyRoleId);
        $faculty2 = $this->makeUser('faculty-immutable2@duty.test', $this->facultyRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId, $entryId] = $this->seedExam($admin);

        $roleId = ExamDutyRole::where('institution_id', $this->institution->id)
            ->where('code', 'INVIGILATOR')
            ->value('id');

        // Create an assignment while the roster is draft.
        $assignmentId = $this->postJson(
            "/api/examinations/{$examId}/duty-assignments",
            [
                'exam_subject_id' => $entryId,
                'user_id' => $faculty1->id,
                'duty_role_id' => $roleId,
            ]
        )->assertCreated()->json('assignment.id');

        // Complete the roster lifecycle.
        Sanctum::actingAs($admin);

        $this->postJson("/api/examinations/{$examId}/duty-roster/submit")
            ->assertOk();

        $this->confirmMfa($officer);
        Sanctum::actingAs($officer);

        $this->postJson("/api/examinations/{$examId}/duty-roster/approve")
            ->assertOk();

        $this->postJson("/api/examinations/{$examId}/duty-roster/publish")
            ->assertOk();

        // 1. Updating an assignment must be rejected.
        $this->patchJson(
            "/api/examinations/{$examId}/duty-assignments/{$assignmentId}",
            ['remarks' => 'Attempted modification after publication.']
        )->assertStatus(409);

        // 2. Deleting an assignment must be rejected.
        $this->deleteJson(
            "/api/examinations/{$examId}/duty-assignments/{$assignmentId}"
        )->assertStatus(409);

        // 3. Auto-assignment must be rejected.
        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/auto-assign",
            [
                'duty_role_id' => $roleId,
                'required_per_slot' => 1,
                'staff_user_ids' => [$faculty2->id],
            ]
        )->assertStatus(409);

        // The published roster must remain unchanged.
        $this->assertDatabaseHas('exam_duty_rosters', [
            'institution_id' => $this->institution->id,
            'exam_id' => $examId,
            'version' => 1,
            'status' => 'published',
        ]);

        // Original assignment must still exist and remain unchanged.
        $this->assertDatabaseHas('exam_duty_assignments', [
            'id' => $assignmentId,
            'exam_subject_id' => $entryId,
            'user_id' => $faculty1->id,
            'status' => 'assigned',
            'remarks' => null,
        ]);

        $this->assertDatabaseCount('exam_duty_assignments', 1);

        // Legacy invigilation record must also remain intact.
        $this->assertDatabaseHas('exam_invigilations', [
            'institution_id' => $this->institution->id,
            'exam_subject_id' => $entryId,
            'user_id' => $faculty1->id,
            'duty' => 'invigilator',
        ]);

        $this->assertDatabaseMissing('exam_invigilations', [
            'institution_id' => $this->institution->id,
            'exam_subject_id' => $entryId,
            'user_id' => $faculty2->id,
        ]);
    }

    public function test_staff_can_accept_or_decline_assignments_after_roster_publication(): void
    {
        $admin = $this->makeUser('admin-postpublish@duty.test', $this->adminRoleId);
        $officer = $this->makeUser('officer-postpublish@duty.test', $this->officerRoleId);
        $faculty1 = $this->makeUser('faculty-accept-postpublish@duty.test', $this->facultyRoleId);
        $faculty2 = $this->makeUser('faculty-decline-postpublish@duty.test', $this->facultyRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId, $entryId] = $this->seedExam($admin);

        $roleId = ExamDutyRole::where('institution_id', $this->institution->id)
            ->where('code', 'INVIGILATOR')
            ->value('id');

        // Create both assignments while the roster is draft.
        $assignment1 = $this->postJson(
            "/api/examinations/{$examId}/duty-assignments",
            [
                'exam_subject_id' => $entryId,
                'user_id' => $faculty1->id,
                'duty_role_id' => $roleId,
            ]
        )->assertCreated()->json('assignment.id');

        $assignment2 = $this->postJson(
            "/api/examinations/{$examId}/duty-assignments",
            [
                'exam_subject_id' => $entryId,
                'user_id' => $faculty2->id,
                'duty_role_id' => $roleId,
            ]
        )->assertCreated()->json('assignment.id');

        // Submit, approve and publish the roster.
        Sanctum::actingAs($admin);

        $this->postJson("/api/examinations/{$examId}/duty-roster/submit")
            ->assertOk();

        $this->confirmMfa($officer);
        Sanctum::actingAs($officer);

        $this->postJson("/api/examinations/{$examId}/duty-roster/approve")
            ->assertOk();

        $this->postJson("/api/examinations/{$examId}/duty-roster/publish")
            ->assertOk();

        // Faculty1 accepts their assignment after publication.
        $this->confirmMfa($faculty1);
        Sanctum::actingAs($faculty1);

        $this->postJson(
            "/api/examinations/{$examId}/duty-assignments/{$assignment1}/respond",
            [
                'response' => 'accepted',
                'remarks' => 'I will attend.',
            ]
        )->assertOk()
            ->assertJsonPath('assignment.status', 'accepted');

        // Faculty2 declines their assignment after publication.
        $this->confirmMfa($faculty2);
        Sanctum::actingAs($faculty2);

        $this->postJson(
            "/api/examinations/{$examId}/duty-assignments/{$assignment2}/respond",
            [
                'response' => 'declined',
                'remarks' => 'I am unavailable.',
            ]
        )->assertOk()
            ->assertJsonPath('assignment.status', 'declined');

        // Both responses must be persisted.
        $this->assertDatabaseHas('exam_duty_assignments', [
            'id' => $assignment1,
            'user_id' => $faculty1->id,
            'status' => 'accepted',
            'remarks' => 'I will attend.',
        ]);

        $this->assertDatabaseHas('exam_duty_assignments', [
            'id' => $assignment2,
            'user_id' => $faculty2->id,
            'status' => 'declined',
            'remarks' => 'I am unavailable.',
        ]);

        $this->assertNotNull(
            ExamDutyAssignment::findOrFail($assignment1)->responded_at
        );

        $this->assertNotNull(
            ExamDutyAssignment::findOrFail($assignment2)->responded_at
        );

        // Staff responses must not change the published roster lifecycle.
        $this->assertDatabaseHas('exam_duty_rosters', [
            'institution_id' => $this->institution->id,
            'exam_id' => $examId,
            'version' => 1,
            'status' => 'published',
        ]);
    }

    public function test_auto_assign_ignores_completed_and_different_session_examinations(): void
    {
        $admin = $this->makeUser('admin-excluded-load@duty.test', $this->adminRoleId);
        $firstStaff = $this->makeUser('a-first@duty.test', $this->facultyRoleId);
        $secondStaff = $this->makeUser('b-second@duty.test', $this->facultyRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId, $targetSubjectId, $programme, $subject] = $this->seedExam($admin);

        $academicSession = $this->createAcademicSession('2026-27');
        $otherSession = $this->createAcademicSession('2027-28');

        $targetExam = Exam::findOrFail($examId);
        $targetExam->academic_session_id = $academicSession->id;
        $targetExam->save();

        [, $completedSubject] = $this->createRelatedExam(
            $programme,
            $subject,
            $academicSession->id,
            'completed',
            'Completed Examination'
        );

        [, $otherSessionSubject] = $this->createRelatedExam(
            $programme,
            $subject,
            $otherSession->id,
            'scheduled',
            'Other Session Examination'
        );

        $roleId = ExamDutyRole::where('institution_id', $this->institution->id)
            ->where('code', 'INVIGILATOR')
            ->value('id');

        // Both assignments must be ignored by the target examination's workload.
        $this->createDutyAssignment($completedSubject->id, $firstStaff, $roleId, $admin);
        $this->createDutyAssignment($otherSessionSubject->id, $firstStaff, $roleId, $admin);

        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/auto-assign",
            [
                'duty_role_id' => $roleId,
                'required_per_slot' => 1,
                'staff_user_ids' => [$firstStaff->id, $secondStaff->id],
            ]
        )->assertOk()->assertJsonPath('created', 1);

        // With excluded workload, the lower user ID wins the tie.
        $this->assertDatabaseHas('exam_duty_assignments', [
            'exam_subject_id' => $targetSubjectId,
            'user_id' => $firstStaff->id,
            'status' => 'assigned',
        ]);
    }

    public function test_roster_workload_includes_other_eligible_examinations_in_same_session(): void
    {
        $admin = $this->makeUser('admin-roster-load@duty.test', $this->adminRoleId);
        $faculty = $this->makeUser('faculty-roster-load@duty.test', $this->facultyRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId, $targetSubjectId, $programme, $subject] = $this->seedExam($admin);

        $academicSession = $this->createAcademicSession('2026-27');

        $targetExam = Exam::findOrFail($examId);
        $targetExam->academic_session_id = $academicSession->id;
        $targetExam->save();

        [, $otherSubject] = $this->createRelatedExam(
            $programme,
            $subject,
            $academicSession->id,
            'published',
            'Published Examination'
        );

        $role = ExamDutyRole::where('institution_id', $this->institution->id)
            ->where('code', 'INVIGILATOR')
            ->firstOrFail();

        $this->createDutyAssignment($otherSubject->id, $faculty, $role->id, $admin);

        $response = $this->getJson(
            "/api/examinations/{$examId}/duty-roster"
        )->assertOk();

        $workloadRow = collect($response->json('workload'))
            ->firstWhere('user.id', $faculty->id);

        $this->assertNotNull($workloadRow);
        $this->assertEquals(1, $workloadRow['duty_count']);
        $this->assertEquals($role->duty_weight, $workloadRow['weighted_load']);
        $this->assertEquals(1, $workloadRow['assigned']);
    }

    public function test_null_academic_session_workload_is_limited_to_target_exam(): void
    {
        $admin = $this->makeUser('admin-null-session@duty.test', $this->adminRoleId);
        $firstStaff = $this->makeUser('a-null-first@duty.test', $this->facultyRoleId);
        $secondStaff = $this->makeUser('b-null-second@duty.test', $this->facultyRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId, $targetSubjectId, $programme, $subject] = $this->seedExam($admin);

        // Both examinations deliberately have a null academic_session_id.
        [, $unrelatedSubject] = $this->createRelatedExam(
            $programme,
            $subject,
            null,
            'scheduled',
            'Unrelated Null Session Examination'
        );

        $roleId = ExamDutyRole::where('institution_id', $this->institution->id)
            ->where('code', 'INVIGILATOR')
            ->value('id');

        $this->createDutyAssignment($unrelatedSubject->id, $firstStaff, $roleId, $admin);

        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/auto-assign",
            [
                'duty_role_id' => $roleId,
                'required_per_slot' => 1,
                'staff_user_ids' => [$firstStaff->id, $secondStaff->id],
            ]
        )->assertOk()->assertJsonPath('created', 1);

        // The unrelated null-session assignment must not increase firstStaff's load.
        $this->assertDatabaseHas('exam_duty_assignments', [
            'exam_subject_id' => $targetSubjectId,
            'user_id' => $firstStaff->id,
            'status' => 'assigned',
        ]);
    }

    public function test_declined_and_replaced_assignments_do_not_increase_workload(): void
    {
        $admin = $this->makeUser('admin-status-load@duty.test', $this->adminRoleId);
        $firstStaff = $this->makeUser('a-status-first@duty.test', $this->facultyRoleId);
        $secondStaff = $this->makeUser('b-status-second@duty.test', $this->facultyRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId, $targetSubjectId, $programme, $subject] = $this->seedExam($admin);

        $academicSession = $this->createAcademicSession('2026-27');

        $targetExam = Exam::findOrFail($examId);
        $targetExam->academic_session_id = $academicSession->id;
        $targetExam->save();

        [, $otherSubject] = $this->createRelatedExam(
            $programme,
            $subject,
            $academicSession->id,
            'scheduled',
            'Other Scheduled Examination'
        );

        $roleId = ExamDutyRole::where('institution_id', $this->institution->id)
            ->where('code', 'INVIGILATOR')
            ->value('id');

        $this->createDutyAssignment(
            $otherSubject->id,
            $firstStaff,
            $roleId,
            $admin,
            'declined'
        );

        $this->createDutyAssignment(
            $otherSubject->id,
            $firstStaff,
            $roleId,
            $admin,
            'replaced'
        );

        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/auto-assign",
            [
                'duty_role_id' => $roleId,
                'required_per_slot' => 1,
                'staff_user_ids' => [$firstStaff->id, $secondStaff->id],
            ]
        )->assertOk()->assertJsonPath('created', 1);

        // Excluded statuses leave both candidates at equal workload.
        $this->assertDatabaseHas('exam_duty_assignments', [
            'exam_subject_id' => $targetSubjectId,
            'user_id' => $firstStaff->id,
            'status' => 'assigned',
        ]);
    }


    public function test_admin_can_submit_a_draft_duty_roster(): void
    {
        $admin = $this->makeUser('admin-submit@duty.test', $this->adminRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        Sanctum::actingAs($admin);

        $response = $this->postJson(
            "/api/examinations/{$examId}/duty-roster/submit"
        )->assertOk()
            ->assertJsonPath('roster.status', 'submitted')
            ->assertJsonPath('roster.submitted_by', $admin->id);

        $this->assertNotNull($response->json('roster.submitted_at'));

        $this->assertDatabaseHas('exam_duty_rosters', [
            'exam_id' => $examId,
            'institution_id' => $this->institution->id,
            'version' => 1,
            'status' => 'submitted',
            'submitted_by' => $admin->id,
        ]);
    }

    public function test_draft_duty_roster_cannot_be_submitted_twice(): void
    {
        $admin = $this->makeUser('admin-submit-twice@duty.test', $this->adminRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/submit"
        )->assertOk();

        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/submit"
        )->assertStatus(409);

        $this->assertDatabaseHas('exam_duty_rosters', [
            'exam_id' => $examId,
            'institution_id' => $this->institution->id,
            'version' => 1,
            'status' => 'submitted',
        ]);
    }

    public function test_non_draft_duty_roster_cannot_be_submitted(): void
    {
        $admin = $this->makeUser('admin-nondraft@duty.test', $this->adminRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        DB::table('exam_duty_rosters')
            ->where('institution_id', $this->institution->id)
            ->where('exam_id', $examId)
            ->update(['status' => 'approved']);

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/submit"
        )->assertStatus(409);

        $this->assertDatabaseHas('exam_duty_rosters', [
            'exam_id' => $examId,
            'institution_id' => $this->institution->id,
            'version' => 1,
            'status' => 'approved',
        ]);
    }

    public function test_faculty_cannot_submit_a_duty_roster(): void
    {
        $admin = $this->makeUser('admin-submit-auth@duty.test', $this->adminRoleId);
        $faculty = $this->makeUser('faculty-submit-auth@duty.test', $this->facultyRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        $this->confirmMfa($faculty);
        Sanctum::actingAs($faculty);

        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/submit"
        )->assertForbidden();

        $this->assertDatabaseHas('exam_duty_rosters', [
            'exam_id' => $examId,
            'institution_id' => $this->institution->id,
            'version' => 1,
            'status' => 'draft',
        ]);
    }

    public function test_admin_cannot_submit_another_institutions_duty_roster(): void
    {
        $admin = $this->makeUser('admin-cross-tenant@duty.test', $this->adminRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        // Create a second institution and its examination.
        $otherInstitution = Institution::create([
            'code' => 'OTHER',
            'name' => 'Other Test College',
            'slug' => 'other',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        $otherProgramme = Programme::create([
            'institution_id' => $otherInstitution->id,
            'code' => 'OTHR',
            'name' => 'Other Programme',
            'level' => 'UG',
            'duration_semesters' => 8,
            'status' => 'active',
        ]);

        $otherExam = Exam::create([
            'institution_id' => $otherInstitution->id,
            'title' => 'Other Institution Examination',
            'kind' => 'end_sem',
            'programme_id' => $otherProgramme->id,
            'semester' => 7,
            'section' => 'A',
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/examinations/{$otherExam->id}/duty-roster/submit"
        )->assertNotFound();

        // The current institution's own roster remains unchanged.
        $this->assertDatabaseHas('exam_duty_rosters', [
            'institution_id' => $this->institution->id,
            'exam_id' => $examId,
            'version' => 1,
            'status' => 'draft',
        ]);
    }

    public function test_admin_cannot_view_or_change_another_institutions_duty_roster(): void
    {
        $admin = $this->makeUser(
            'admin-cross-tenant-access@duty.test',
            $this->adminRoleId
        );

        $officer = $this->makeUser(
            'officer-cross-tenant-access@duty.test',
            $this->officerRoleId
        );

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);

        // Create an examination in the current institution.
        [$ownExamId] = $this->seedExam($admin);

        // Create a second institution and examination.
        $otherInstitution = Institution::create([
            'code' => 'OTHER',
            'name' => 'Other Test College',
            'slug' => 'other',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        $otherProgramme = Programme::create([
            'institution_id' => $otherInstitution->id,
            'code' => 'OTHR',
            'name' => 'Other Programme',
            'level' => 'UG',
            'duration_semesters' => 8,
            'status' => 'active',
        ]);

        $otherExam = Exam::create([
            'institution_id' => $otherInstitution->id,
            'title' => 'Other Institution Examination',
            'kind' => 'end_sem',
            'programme_id' => $otherProgramme->id,
            'semester' => 7,
            'section' => 'A',
            'status' => 'scheduled',
        ]);

        $auditCountBefore = DB::table('audit_logs')->count();

        // Attempt to access the other institution's roster.
        Sanctum::actingAs($admin);

        $this->getJson(
            "/api/examinations/{$otherExam->id}/duty-roster"
        )->assertNotFound();

        $this->getJson(
            "/api/examinations/{$otherExam->id}/duty-roster/print"
        )->assertNotFound();

        // Attempt lifecycle operations as an institution administrator.
        $this->postJson(
            "/api/examinations/{$otherExam->id}/duty-roster/submit"
        )->assertNotFound();

        // Attempt approval, rejection and publication as an authorized
        // examination officer from the wrong institution.
        $this->confirmMfa($officer);
        Sanctum::actingAs($officer);

        $this->postJson(
            "/api/examinations/{$otherExam->id}/duty-roster/approve"
        )->assertNotFound();

        $this->postJson(
            "/api/examinations/{$otherExam->id}/duty-roster/reject",
            ['rejection_reason' => 'Cross-institution access test']
        )->assertNotFound();

        $this->postJson(
            "/api/examinations/{$otherExam->id}/duty-roster/publish"
        )->assertNotFound();

        // No audit event should be generated by rejected access attempts.
        $this->assertSame(
            $auditCountBefore,
            DB::table('audit_logs')->count()
        );

        // The current institution's own examination remains accessible.
        Sanctum::actingAs($admin);

        $this->getJson(
            "/api/examinations/{$ownExamId}/duty-roster"
        )->assertOk();
    }

    public function test_independent_examination_officer_can_approve_submitted_duty_roster(): void
    {
        $admin = $this->makeUser('admin-approve@duty.test', $this->adminRoleId);
        $officer = $this->makeUser('officer-approve@duty.test', $this->officerRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        // Admin submits the roster.
        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/submit"
        )->assertOk()
            ->assertJsonPath('roster.status', 'submitted');

        // Independent examination officer approves it.
        $this->confirmMfa($officer);
        Sanctum::actingAs($officer);

        $response = $this->postJson(
            "/api/examinations/{$examId}/duty-roster/approve"
        )->assertOk()
            ->assertJsonPath('roster.status', 'approved')
            ->assertJsonPath('roster.approved_by', $officer->id);

        $this->assertNotNull($response->json('roster.approved_at'));

        $this->assertDatabaseHas('exam_duty_rosters', [
            'institution_id' => $this->institution->id,
            'exam_id' => $examId,
            'version' => 1,
            'status' => 'approved',
            'submitted_by' => $admin->id,
            'approved_by' => $officer->id,
        ]);
    }

    public function test_roster_submitter_cannot_approve_own_duty_roster(): void
    {
        $admin = $this->makeUser('admin-self-approve@duty.test', $this->adminRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/submit"
        )->assertOk();

        // The same admin attempts to approve.
        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/approve"
        )->assertForbidden();

        // Roster must remain submitted.
        $this->assertDatabaseHas('exam_duty_rosters', [
            'institution_id' => $this->institution->id,
            'exam_id' => $examId,
            'version' => 1,
            'status' => 'submitted',
            'submitted_by' => $admin->id,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function test_draft_duty_roster_cannot_be_approved(): void
    {
        $admin = $this->makeUser('admin-draft-approve@duty.test', $this->adminRoleId);
        $officer = $this->makeUser('officer-draft-approve@duty.test', $this->officerRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        $this->confirmMfa($officer);
        Sanctum::actingAs($officer);

        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/approve"
        )->assertStatus(409);

        $this->assertDatabaseHas('exam_duty_rosters', [
            'institution_id' => $this->institution->id,
            'exam_id' => $examId,
            'version' => 1,
            'status' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function test_faculty_cannot_approve_a_duty_roster(): void
    {
        $admin = $this->makeUser('admin-faculty-approve@duty.test', $this->adminRoleId);
        $faculty = $this->makeUser('faculty-approve@duty.test', $this->facultyRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        // Admin submits the roster.
        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/submit"
        )->assertOk();

        // Faculty attempts approval.
        $this->confirmMfa($faculty);
        Sanctum::actingAs($faculty);

        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/approve"
        )->assertForbidden();

        $this->assertDatabaseHas('exam_duty_rosters', [
            'institution_id' => $this->institution->id,
            'exam_id' => $examId,
            'version' => 1,
            'status' => 'submitted',
            'approved_by' => null,
        ]);
    }

    public function test_admin_cannot_approve_another_institutions_duty_roster(): void
    {
        $admin = $this->makeUser('admin-cross-approve@duty.test', $this->adminRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        // Create another institution.
        $otherInstitution = Institution::create([
            'code' => 'OTHER',
            'name' => 'Other Test College',
            'slug' => 'other',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        $otherProgramme = Programme::create([
            'institution_id' => $otherInstitution->id,
            'code' => 'OTHR',
            'name' => 'Other Programme',
            'level' => 'UG',
            'duration_semesters' => 8,
            'status' => 'active',
        ]);

        $otherExam = Exam::create([
            'institution_id' => $otherInstitution->id,
            'title' => 'Other Institution Examination',
            'kind' => 'end_sem',
            'programme_id' => $otherProgramme->id,
            'semester' => 7,
            'section' => 'A',
            'status' => 'scheduled',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/examinations/{$otherExam->id}/duty-roster/approve"
        )->assertNotFound();

        // The current institution's roster remains unchanged.
        $this->assertDatabaseHas('exam_duty_rosters', [
            'institution_id' => $this->institution->id,
            'exam_id' => $examId,
            'version' => 1,
            'status' => 'draft',
        ]);
    }


    public function test_independent_examination_officer_can_reject_submitted_duty_roster(): void
    {
        $admin = $this->makeUser('admin-reject@duty.test', $this->adminRoleId);
        $officer = $this->makeUser('officer-reject@duty.test', $this->officerRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        // Admin submits the roster.
        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/submit"
        )->assertOk();

        // An independent examination officer rejects it.
        $this->confirmMfa($officer);
        Sanctum::actingAs($officer);

        $reason = 'Duty allocation requires correction. Please review staff workload.';

        $response = $this->postJson(
            "/api/examinations/{$examId}/duty-roster/reject",
            ['rejection_reason' => $reason]
        )->assertOk()
            ->assertJsonPath('roster.status', 'rejected')
            ->assertJsonPath('roster.rejected_by', $officer->id)
            ->assertJsonPath('roster.rejection_reason', $reason);

        $this->assertNotNull($response->json('roster.rejected_at'));

        $this->assertDatabaseHas('exam_duty_rosters', [
            'institution_id' => $this->institution->id,
            'exam_id' => $examId,
            'version' => 1,
            'status' => 'rejected',
            'submitted_by' => $admin->id,
            'rejected_by' => $officer->id,
            'rejection_reason' => $reason,
        ]);
    }

    public function test_roster_submitter_cannot_reject_own_duty_roster(): void
    {
        $admin = $this->makeUser('admin-self-reject@duty.test', $this->adminRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/submit"
        )->assertOk();

        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/reject",
            ['rejection_reason' => 'Attempted self-rejection.']
        )->assertForbidden();

        $this->assertDatabaseHas('exam_duty_rosters', [
            'institution_id' => $this->institution->id,
            'exam_id' => $examId,
            'version' => 1,
            'status' => 'submitted',
            'submitted_by' => $admin->id,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);
    }

    public function test_draft_duty_roster_cannot_be_rejected(): void
    {
        $admin = $this->makeUser('admin-draft-reject@duty.test', $this->adminRoleId);
        $officer = $this->makeUser('officer-draft-reject@duty.test', $this->officerRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        $this->confirmMfa($officer);
        Sanctum::actingAs($officer);

        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/reject",
            ['rejection_reason' => 'Cannot reject a draft roster.']
        )->assertStatus(409);

        $this->assertDatabaseHas('exam_duty_rosters', [
            'institution_id' => $this->institution->id,
            'exam_id' => $examId,
            'version' => 1,
            'status' => 'draft',
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);
    }

    public function test_faculty_cannot_reject_a_duty_roster(): void
    {
        $admin = $this->makeUser('admin-faculty-reject@duty.test', $this->adminRoleId);
        $faculty = $this->makeUser('faculty-reject@duty.test', $this->facultyRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        // Admin submits the roster.
        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/submit"
        )->assertOk();

        // Faculty attempts to reject it.
        $this->confirmMfa($faculty);
        Sanctum::actingAs($faculty);

        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/reject",
            ['rejection_reason' => 'Faculty rejection attempt.']
        )->assertForbidden();

        $this->assertDatabaseHas('exam_duty_rosters', [
            'institution_id' => $this->institution->id,
            'exam_id' => $examId,
            'version' => 1,
            'status' => 'submitted',
            'rejected_by' => null,
            'rejected_at' => null,
        ]);
    }

    public function test_rejection_reason_is_mandatory(): void
    {
        $admin = $this->makeUser('admin-reason-reject@duty.test', $this->adminRoleId);
        $officer = $this->makeUser('officer-reason-reject@duty.test', $this->officerRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        // Admin submits the roster.
        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/submit"
        )->assertOk();

        // Officer attempts rejection without a reason.
        $this->confirmMfa($officer);
        Sanctum::actingAs($officer);

        $this->postJson(
            "/api/examinations/{$examId}/duty-roster/reject",
            []
        )->assertStatus(422)
            ->assertJsonValidationErrors(['rejection_reason']);

        // The roster must remain submitted.
        $this->assertDatabaseHas('exam_duty_rosters', [
            'institution_id' => $this->institution->id,
            'exam_id' => $examId,
            'version' => 1,
            'status' => 'submitted',
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);
    }

    public function test_independent_examination_officer_can_publish_approved_duty_roster(): void
    {
        $admin = $this->makeUser('publish-admin@duty.test', $this->adminRoleId);
        $officer = $this->makeUser('publish-officer@duty.test', $this->officerRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        Sanctum::actingAs($admin);
        $this->postJson("/api/examinations/{$examId}/duty-roster/submit")
            ->assertOk();

        $this->confirmMfa($officer);
        Sanctum::actingAs($officer);

        $this->postJson("/api/examinations/{$examId}/duty-roster/approve")
            ->assertOk();

        $response = $this->postJson(
            "/api/examinations/{$examId}/duty-roster/publish"
        );

        $response->assertOk()
            ->assertJsonPath('message', 'Duty roster published successfully.')
            ->assertJsonPath('roster.status', 'published')
            ->assertJsonPath('roster.published_by', $officer->id)
            ->assertJsonStructure([
                'roster' => ['published_at'],
            ]);
    }

    public function test_draft_duty_roster_cannot_be_published(): void
    {
        $admin = $this->makeUser('publish-draft@duty.test', $this->adminRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        Sanctum::actingAs($admin);

        $this->postJson("/api/examinations/{$examId}/duty-roster/publish")
            ->assertStatus(409);
    }

    public function test_submitted_duty_roster_cannot_be_published(): void
    {
        $admin = $this->makeUser('publish-submitted@duty.test', $this->adminRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        Sanctum::actingAs($admin);

        $this->postJson("/api/examinations/{$examId}/duty-roster/submit")
            ->assertOk();

        $this->postJson("/api/examinations/{$examId}/duty-roster/publish")
            ->assertStatus(409);
    }

    public function test_rejected_duty_roster_cannot_be_published(): void
    {
        $admin = $this->makeUser('publish-rejected-admin@duty.test', $this->adminRoleId);
        $officer = $this->makeUser('publish-rejected-officer@duty.test', $this->officerRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        Sanctum::actingAs($admin);
        $this->postJson("/api/examinations/{$examId}/duty-roster/submit")
            ->assertOk();

        $this->confirmMfa($officer);
        Sanctum::actingAs($officer);

        $this->postJson("/api/examinations/{$examId}/duty-roster/reject", [
            'rejection_reason' => 'Please correct the roster.',
        ])->assertOk();

        $this->postJson("/api/examinations/{$examId}/duty-roster/publish")
            ->assertStatus(409);
    }

    public function test_faculty_cannot_publish_approved_duty_roster(): void
    {
        $admin = $this->makeUser('publish-faculty-admin@duty.test', $this->adminRoleId);
        $officer = $this->makeUser('publish-faculty-officer@duty.test', $this->officerRoleId);
        $faculty = $this->makeUser('publish-faculty@duty.test', $this->facultyRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        Sanctum::actingAs($admin);
        $this->postJson("/api/examinations/{$examId}/duty-roster/submit")
            ->assertOk();

        $this->confirmMfa($officer);
        Sanctum::actingAs($officer);

        $this->postJson("/api/examinations/{$examId}/duty-roster/approve")
            ->assertOk();

        Sanctum::actingAs($faculty);

        $this->postJson("/api/examinations/{$examId}/duty-roster/publish")
            ->assertForbidden();
    }

    public function test_published_duty_roster_cannot_be_published_twice(): void
    {
        $admin = $this->makeUser('publish-twice-admin@duty.test', $this->adminRoleId);

        $officer = $this->makeUser('publish-twice-officer@duty.test', $this->officerRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        Sanctum::actingAs($admin);
        $this->postJson("/api/examinations/{$examId}/duty-roster/submit")
            ->assertOk();

        $this->confirmMfa($officer);
        Sanctum::actingAs($officer);

        $this->postJson("/api/examinations/{$examId}/duty-roster/approve")
            ->assertOk();

        $this->postJson("/api/examinations/{$examId}/duty-roster/publish")
            ->assertOk();

        $this->postJson("/api/examinations/{$examId}/duty-roster/publish")
            ->assertStatus(409);
    }


    public function test_duty_roster_lifecycle_records_audit_events_and_valid_hash_chain(): void
    {
        $admin = $this->makeUser('audit-lifecycle-admin@duty.test', $this->adminRoleId);
        $officer = $this->makeUser('audit-lifecycle-officer@duty.test', $this->officerRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        // Submit.
        Sanctum::actingAs($admin);

        $this->postJson("/api/examinations/{$examId}/duty-roster/submit")
            ->assertOk();

        // Approve and publish through an independent examination officer.
        $this->confirmMfa($officer);
        Sanctum::actingAs($officer);

        $this->postJson("/api/examinations/{$examId}/duty-roster/approve")
            ->assertOk();

        $this->postJson("/api/examinations/{$examId}/duty-roster/publish")
            ->assertOk();

        $roster = DB::table('exam_duty_rosters')
            ->where('institution_id', $this->institution->id)
            ->where('exam_id', $examId)
            ->where('version', 1)
            ->first();

        $this->assertNotNull($roster);

        $events = AuditLog::query()
            ->where('institution_id', $this->institution->id)
            ->where('module', 'examinations')
            ->where('subject_type', 'ExamDutyRoster')
            ->where('subject_id', (string) $roster->id)
            ->orderBy('id')
            ->get();

        $this->assertSame([
            'exam.duty_roster_submitted',
            'exam.duty_roster_approved',
            'exam.duty_roster_published',
        ], $events->pluck('event')->all());

        $this->assertSame(
            [$admin->id, $officer->id, $officer->id],
            $events->pluck('actor_user_id')->all()
        );

        $this->assertSame(
            ['draft', 'submitted', 'approved'],
            $events->map(fn($event) => $event->old_values['status'] ?? null)->all()
        );

        $this->assertSame(
            ['submitted', 'approved', 'published'],
            $events->map(fn($event) => $event->new_values['status'] ?? null)->all()
        );

        foreach ($events as $event) {
            $this->assertNotNull($event->prev_hash);
            $this->assertNotNull($event->entry_hash);
        }

        [$valid, $checked, $firstBroken] = AuditService::verifyChain();

        $this->assertTrue($valid, 'The audit hash chain should be valid.');
        $this->assertGreaterThanOrEqual(3, $checked);
        $this->assertNull($firstBroken);
    }


    public function test_rejected_duty_roster_records_rejection_reason_in_audit_trail(): void
    {
        $admin = $this->makeUser('audit-reject-admin@duty.test', $this->adminRoleId);
        $officer = $this->makeUser('audit-reject-officer@duty.test', $this->officerRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId] = $this->seedExam($admin);

        Sanctum::actingAs($admin);

        $this->postJson("/api/examinations/{$examId}/duty-roster/submit")
            ->assertOk();

        $this->confirmMfa($officer);
        Sanctum::actingAs($officer);

        $reason = 'Please review staff workload and correct the allocation.';

        $this->postJson("/api/examinations/{$examId}/duty-roster/reject", [
            'rejection_reason' => $reason,
        ])->assertOk();

        $roster = DB::table('exam_duty_rosters')
            ->where('institution_id', $this->institution->id)
            ->where('exam_id', $examId)
            ->where('version', 1)
            ->first();

        $this->assertNotNull($roster);

        $event = AuditLog::query()
            ->where('institution_id', $this->institution->id)
            ->where('event', 'exam.duty_roster_rejected')
            ->where('subject_type', 'ExamDutyRoster')
            ->where('subject_id', (string) $roster->id)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($officer->id, $event->actor_user_id);
        $this->assertSame('submitted', $event->old_values['status'] ?? null);
        $this->assertSame('rejected', $event->new_values['status'] ?? null);
        $this->assertSame($reason, $event->new_values['rejection_reason'] ?? null);
        $this->assertNotNull($event->entry_hash);
    }
}
