<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\Exam;
use Illuminate\Support\Facades\DB;
use App\Models\ExamDutyAssignment;
use App\Models\ExamDutyRole;
use App\Models\ExamDutyRoster;
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

        $roster = ExamDutyRoster::create([
            'institution_id' => $this->institution->id,
            'exam_id' => $exam->id,
            'version' => 1,
            'status' => 'draft',
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
        $examSubject = ExamSubject::findOrFail($examSubjectId);

        $roster = ExamDutyRoster::where('institution_id', $this->institution->id)
            ->where('exam_id', $examSubject->exam_id)
            ->orderByDesc('version')
            ->firstOrFail();

        return ExamDutyAssignment::create([
            'institution_id' => $this->institution->id,
            'exam_subject_id' => $examSubjectId,
            'roster_id' => $roster->id,
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

    private function publishRoster(int $examId, User $admin, string $officerEmail): User
    {
        $officer = $this->makeUser($officerEmail, $this->officerRoleId);

        // The roster creator submits it.
        Sanctum::actingAs($admin);

        $this->postJson("/api/examinations/{$examId}/duty-roster/submit")
            ->assertOk()
            ->assertJsonPath('roster.status', 'submitted');

        // An independent examination officer approves and publishes it.
        $this->confirmMfa($officer);
        Sanctum::actingAs($officer);

        $this->postJson("/api/examinations/{$examId}/duty-roster/approve")
            ->assertOk()
            ->assertJsonPath('roster.status', 'approved');

        $this->postJson("/api/examinations/{$examId}/duty-roster/publish")
            ->assertOk()
            ->assertJsonPath('roster.status', 'published');

        return $officer;
    }


    /**
     * Build a published roster with two accepted assignments, ready for swap tests.
     */
    private function prepareSwapScenario(string $suffix = 'basic'): array
    {
        $admin = $this->makeUser("swap-admin-{$suffix}@duty.test", $this->adminRoleId);
        $firstStaff = $this->makeUser("swap-first-{$suffix}@duty.test", $this->facultyRoleId);
        $secondStaff = $this->makeUser("swap-second-{$suffix}@duty.test", $this->facultyRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId, $entryId] = $this->seedExam($admin);

        $roleId = ExamDutyRole::where('institution_id', $this->institution->id)
            ->where('code', 'INVIGILATOR')
            ->value('id');

        $firstAssignmentId = $this->postJson(
            "/api/examinations/{$examId}/duty-assignments",
            [
                'exam_subject_id' => $entryId,
                'user_id' => $firstStaff->id,
                'duty_role_id' => $roleId,
            ]
        )->assertCreated()->json('assignment.id');

        $secondAssignmentId = $this->postJson(
            "/api/examinations/{$examId}/duty-assignments",
            [
                'exam_subject_id' => $entryId,
                'user_id' => $secondStaff->id,
                'duty_role_id' => $roleId,
            ]
        )->assertCreated()->json('assignment.id');

        $officer = $this->publishRoster(
            $examId,
            $admin,
            "swap-officer-{$suffix}@duty.test"
        );

        foreach ([
            [$firstStaff, $firstAssignmentId],
            [$secondStaff, $secondAssignmentId],
        ] as [$staff, $assignmentId]) {
            $this->confirmMfa($staff);
            Sanctum::actingAs($staff);

            $this->postJson(
                "/api/examinations/{$examId}/duty-assignments/{$assignmentId}/respond",
                ['response' => 'accepted', 'remarks' => 'Accepted before swap request.']
            )->assertOk()->assertJsonPath('assignment.status', 'accepted');
        }

        return compact(
            'admin',
            'officer',
            'firstStaff',
            'secondStaff',
            'examId',
            'entryId',
            'roleId',
            'firstAssignmentId',
            'secondAssignmentId'
        );
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

    public function test_assign_duty_creates_draft_assignment_and_syncs_legacy_only_after_publish(): void
    {
        $admin = $this->makeUser('admin@duty.test', $this->adminRoleId);
        $faculty = $this->makeUser('faculty@duty.test', $this->facultyRoleId);
        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);
        [$examId, $entryId] = $this->seedExam($admin);
        $roleId = ExamDutyRole::where('institution_id', $this->institution->id)
            ->where('code', 'CHIEF_SUPERINTENDENT')
            ->value('id');

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

        // A draft assignment must not appear in the published-roster projection.
        $this->assertDatabaseMissing('exam_invigilations', [
            'institution_id' => $this->institution->id,
            'exam_subject_id' => $entryId,
            'user_id' => $faculty->id,
        ]);

        $roster = $this->getJson("/api/examinations/{$examId}/duty-roster")->assertOk();
        $this->assertEquals($faculty->id, $roster->json('slots.0.assignments.0.user.id'));
        $this->assertEquals('CHIEF_SUPERINTENDENT', $roster->json('slots.0.assignments.0.duty_role.code'));

        // Publishing makes the assignment visible in the legacy projection.
        $this->publishRoster($examId, $admin, 'officer-chief-sync@duty.test');

        $this->assertDatabaseHas('exam_invigilations', [
            'institution_id' => $this->institution->id,
            'exam_subject_id' => $entryId,
            'user_id' => $faculty->id,
            'duty' => 'chief',
        ]);
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

        // Create an assignment in version 1 while it is still a draft.
        $existingResponse = $this->postJson(
            "/api/examinations/{$examId}/duty-assignments",
            [
                'exam_subject_id' => $entryId,
                'user_id' => $faculty1->id,
                'duty_role_id' => $roleId,
            ]
        )->assertCreated();

        $existingAssignmentId = $existingResponse->json('assignment.id');

        // Publish version 1 so that its assignment becomes the legacy projection.
        $this->publishRoster($examId, $admin, 'officer-roster-lock@duty.test');

        $this->assertDatabaseHas('exam_invigilations', [
            'institution_id' => $this->institution->id,
            'exam_subject_id' => $entryId,
            'user_id' => $faculty1->id,
            'duty' => 'invigilator',
        ]);

        // Create version 2 and submit it. Structural changes are now prohibited.
        Sanctum::actingAs($admin);
        $this->postJson("/api/examinations/{$examId}/duty-roster/new-version")
            ->assertCreated()
            ->assertJsonPath('roster.version', 2);

        $this->postJson("/api/examinations/{$examId}/duty-roster/submit")
            ->assertOk()
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

        // Version 1 and its copied version 2 assignment remain; no extra row was added.
        $this->assertDatabaseCount('exam_duty_assignments', 2);

        $this->assertDatabaseHas('exam_duty_assignments', [
            'id' => $existingAssignmentId,
            'exam_subject_id' => $entryId,
            'user_id' => $faculty1->id,
            'status' => 'assigned',
        ]);

        // Submitting version 2 must not change the version 1 published projection.
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


    public function test_declined_assignment_is_preserved_when_new_roster_version_is_created(): void
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

        // Publish version 1 before staff responses.
        $this->publishRoster(
            $examId,
            $admin,
            'officer-replacement@duty.test'
        );

        // Faculty1 declines the published assignment.
        Sanctum::actingAs($faculty1);

        $this->postJson(
            "/api/examinations/{$examId}/duty-assignments/{$originalAssignmentId}/respond",
            [
                'response' => 'declined',
                'remarks' => 'Unable to attend.',
            ]
        )->assertOk();

        // Preserve the original declined assignment.
        $originalAssignment = ExamDutyAssignment::findOrFail($originalAssignmentId);

        $this->assertEquals('declined', $originalAssignment->status);
        $this->assertNotNull($originalAssignment->responded_at);

        $originalRespondedAt = $originalAssignment->responded_at;
        $version1RosterId = $originalAssignment->roster_id;

        // Admin creates version 2.
        Sanctum::actingAs($admin);

        $versionResponse = $this->postJson(
            "/api/examinations/{$examId}/duty-roster/new-version"
        )->assertCreated();

        $versionResponse
            ->assertJsonPath('roster.version', 2)
            ->assertJsonPath('roster.status', 'draft');

        $version2RosterId = $versionResponse->json('roster.id');

        // The declined assignment must not be copied into version 2.
        $this->assertDatabaseMissing('exam_duty_assignments', [
            'roster_id' => $version2RosterId,
            'user_id' => $faculty1->id,
        ]);

        // Assign the duty to faculty2 in version 2.
        $newAssignmentResponse = $this->postJson(
            "/api/examinations/{$examId}/duty-assignments",
            [
                'exam_subject_id' => $entryId,
                'user_id' => $faculty2->id,
                'duty_role_id' => $roleId,
            ]
        )->assertCreated();

        $newAssignmentId = $newAssignmentResponse->json('assignment.id');

        // Verify the original assignment remains unchanged in version 1.
        $originalAssignment->refresh();

        $this->assertEquals('declined', $originalAssignment->status);
        $this->assertEquals($version1RosterId, $originalAssignment->roster_id);
        $this->assertEquals(
            $originalRespondedAt->toDateTimeString(),
            $originalAssignment->responded_at->toDateTimeString()
        );

        // Verify the new assignment belongs to version 2.
        $newAssignment = ExamDutyAssignment::findOrFail($newAssignmentId);

        $this->assertEquals($faculty2->id, $newAssignment->user_id);
        $this->assertEquals($version2RosterId, $newAssignment->roster_id);
        $this->assertEquals('assigned', $newAssignment->status);

        // Both historical and new assignments must exist.
        $this->assertDatabaseCount('exam_duty_assignments', 2);

        // Version 2 is only a draft: it must not alter the legacy projection.
        // Faculty1 declined in the published version 1, so its mapping was removed.
        $this->assertDatabaseMissing('exam_invigilations', [
            'institution_id' => $this->institution->id,
            'exam_subject_id' => $entryId,
            'user_id' => $faculty1->id,
        ]);

        $this->assertDatabaseMissing('exam_invigilations', [
            'institution_id' => $this->institution->id,
            'exam_subject_id' => $entryId,
            'user_id' => $faculty2->id,
        ]);

        // Publish version 2; only now should Faculty2 appear in the projection.
        $this->publishRoster(
            $examId,
            $admin,
            'officer-replacement-version2@duty.test'
        );

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


    public function test_roster_history_returns_all_versions_with_lifecycle_metadata(): void
    {
        $admin = $this->makeUser('admin-history@duty.test', $this->adminRoleId);
        $faculty = $this->makeUser('faculty-history@duty.test', $this->facultyRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);

        [$examId, $entryId] = $this->seedExam($admin);

        $roleId = ExamDutyRole::where('institution_id', $this->institution->id)
            ->where('code', 'INVIGILATOR')
            ->value('id');

        // Create an assignment in version 1.
        $this->postJson(
            "/api/examinations/{$examId}/duty-assignments",
            [
                'exam_subject_id' => $entryId,
                'user_id' => $faculty->id,
                'duty_role_id' => $roleId,
            ]
        )->assertCreated();

        // Submit, approve and publish version 1.
        $officer = $this->publishRoster(
            $examId,
            $admin,
            'officer-history@duty.test'
        );

        // Create version 2.
        Sanctum::actingAs($admin);

        $versionResponse = $this->postJson(
            "/api/examinations/{$examId}/duty-roster/new-version"
        )->assertCreated();

        $versionResponse
            ->assertJsonPath('roster.version', 2)
            ->assertJsonPath('roster.status', 'draft');

        // Retrieve roster history.
        $historyResponse = $this->getJson(
            "/api/examinations/{$examId}/duty-roster/history"
        )->assertOk();

        $historyResponse->assertJsonStructure([
            'exam_id',
            'versions' => [
                '*' => [
                    'id',
                    'version',
                    'status',
                    'submitted_by',
                    'submitted_at',
                    'approved_by',
                    'approved_at',
                    'rejected_by',
                    'rejected_at',
                    'rejection_reason',
                    'published_by',
                    'published_at',
                    'assignment_count',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);

        $versions = $historyResponse->json('versions');

        // Both versions must be returned.
        $this->assertCount(2, $versions);

        // Newest version must appear first.
        $this->assertEquals(2, $versions[0]['version']);
        $this->assertEquals(1, $versions[1]['version']);

        // Version 2 must remain a draft.
        $this->assertEquals('draft', $versions[0]['status']);
        $this->assertNull($versions[0]['submitted_by']);
        $this->assertNull($versions[0]['approved_by']);
        $this->assertNull($versions[0]['published_by']);

        // Version 1 must retain its published lifecycle.
        $version1 = $versions[1];

        $this->assertEquals('published', $version1['status']);

        $this->assertEquals($admin->id, $version1['submitted_by']['id']);
        $this->assertEquals($admin->name, $version1['submitted_by']['name']);
        $this->assertNotNull($version1['submitted_at']);

        $this->assertEquals($officer->id, $version1['approved_by']['id']);
        $this->assertEquals($officer->name, $version1['approved_by']['name']);
        $this->assertNotNull($version1['approved_at']);

        $this->assertEquals($officer->id, $version1['published_by']['id']);
        $this->assertEquals($officer->name, $version1['published_by']['name']);
        $this->assertNotNull($version1['published_at']);

        // Both versions have one assignment.
        // Version 2 contains a new copy of the effective Version 1 assignment.
        $this->assertEquals(1, $version1['assignment_count']);
        $this->assertEquals(1, $versions[0]['assignment_count']);

        $version1Assignment = ExamDutyAssignment::where('roster_id', $version1['id'])
            ->firstOrFail();

        $version2Assignment = ExamDutyAssignment::where('roster_id', $versions[0]['id'])
            ->firstOrFail();

        // The copied assignment must be a new database record.
        $this->assertNotEquals(
            $version1Assignment->id,
            $version2Assignment->id
        );

        // The assignment must retain the same exam subject, staff member and duty role.
        $this->assertEquals(
            $version1Assignment->exam_subject_id,
            $version2Assignment->exam_subject_id
        );

        $this->assertEquals(
            $version1Assignment->user_id,
            $version2Assignment->user_id
        );

        $this->assertEquals(
            $version1Assignment->duty_role_id,
            $version2Assignment->duty_role_id
        );

        // The copied assignment must require a fresh staff response.
        $this->assertEquals('assigned', $version2Assignment->status);
        $this->assertNull($version2Assignment->responded_at);
        $this->assertNull($version2Assignment->remarks);

        // The copied assignment must not carry a replacement link.
        $this->assertNull($version2Assignment->replaced_by_assignment_id);

        // Version 1 was not rejected.
        $this->assertNull($version1['rejected_by']);
        $this->assertNull($version1['rejected_at']);
        $this->assertNull($version1['rejection_reason']);

        $this->assertEquals($examId, $historyResponse->json('exam_id'));
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
        $this->publishRoster(
            $examId,
            $admin,
            'officer-security@duty.test'
        );

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
        $this->publishRoster(
            $examId,
            $admin,
            'officer-security@duty.test'
        );

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

    public function test_roster_workload_counts_only_latest_version_assignments(): void
    {
        $admin = $this->makeUser('admin-version-load@duty.test', $this->adminRoleId);
        $faculty = $this->makeUser('faculty-version-load@duty.test', $this->facultyRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);

        [$examId, $entryId] = $this->seedExam($admin);

        $role = ExamDutyRole::where('institution_id', $this->institution->id)
            ->where('code', 'INVIGILATOR')
            ->firstOrFail();

        // Create one assignment in Version 1.
        $originalAssignment = $this->postJson(
            "/api/examinations/{$examId}/duty-assignments",
            [
                'exam_subject_id' => $entryId,
                'user_id' => $faculty->id,
                'duty_role_id' => $role->id,
            ]
        )->assertCreated()->json('assignment');

        $version1RosterId = $originalAssignment['roster_id'];

        // Publish Version 1.
        $this->publishRoster(
            $examId,
            $admin,
            'officer-version-load@duty.test'
        );

        // Create Version 2. The effective assignment is copied as a new row.
        Sanctum::actingAs($admin);

        $version2Response = $this->postJson(
            "/api/examinations/{$examId}/duty-roster/new-version"
        )->assertCreated();

        $version2RosterId = $version2Response->json('roster.id');

        $version2Assignment = ExamDutyAssignment::where('roster_id', $version2RosterId)
            ->firstOrFail();

        $this->assertNotEquals(
            $originalAssignment['id'],
            $version2Assignment->id
        );

        // Both historical records exist, but only Version 2 counts.
        $this->assertDatabaseCount('exam_duty_assignments', 2);

        $response = $this->getJson(
            "/api/examinations/{$examId}/duty-roster"
        )->assertOk();

        $workloadRow = collect($response->json('workload'))
            ->firstWhere('user.id', $faculty->id);

        $this->assertNotNull($workloadRow);

        $this->assertEquals(1, $workloadRow['duty_count']);
        $this->assertEquals(
            (int) $role->duty_weight,
            $workloadRow['weighted_load']
        );
        $this->assertEquals(0, $workloadRow['accepted']);
        $this->assertEquals(1, $workloadRow['assigned']);

        // Confirm the historical assignment remains in Version 1.
        $this->assertEquals(
            $version1RosterId,
            $originalAssignment['roster_id']
        );
    }

    public function test_auto_assign_uses_latest_roster_versions_for_workload(): void
    {
        $admin = $this->makeUser('admin-auto-version@duty.test', $this->adminRoleId);
        $loadedStaff = $this->makeUser('a-loaded-version@duty.test', $this->facultyRoleId);
        $freeStaff = $this->makeUser('b-free-version@duty.test', $this->facultyRoleId);

        $this->seed(\Database\Seeders\ExamDefaultsSeeder::class);

        [$examId, $targetSubjectId, $programme, $subject] = $this->seedExam($admin);

        $academicSession = $this->createAcademicSession('2026-27');

        $targetExam = Exam::findOrFail($examId);
        $targetExam->academic_session_id = $academicSession->id;
        $targetExam->save();

        // Create another eligible examination in the same academic session.
        [$otherExam, $otherSubject] = $this->createRelatedExam(
            $programme,
            $subject,
            $academicSession->id,
            'scheduled',
            'Other Versioned Examination'
        );

        $role = ExamDutyRole::where('institution_id', $this->institution->id)
            ->where('code', 'INVIGILATOR')
            ->firstOrFail();

        // Create an assignment for loadedStaff in Version 1.
        $assignment = $this->postJson(
            "/api/examinations/{$otherExam->id}/duty-assignments",
            [
                'exam_subject_id' => $otherSubject->id,
                'user_id' => $loadedStaff->id,
                'duty_role_id' => $role->id,
            ]
        )->assertCreated();

        // Publish Version 1 and create Version 2.
        $this->publishRoster(
            $otherExam->id,
            $admin,
            'officer-auto-version@duty.test'
        );

        Sanctum::actingAs($admin);

        $versionResponse = $this->postJson(
            "/api/examinations/{$otherExam->id}/duty-roster/new-version"
        )->assertCreated();

        $version2RosterId = $versionResponse->json('roster.id');

        // Verify that the copied assignment exists.
        $this->assertDatabaseHas('exam_duty_assignments', [
            'roster_id' => $version2RosterId,
            'user_id' => $loadedStaff->id,
            'status' => 'assigned',
        ]);

        $this->assertDatabaseCount('exam_duty_assignments', 2);

        // Auto-assign the target examination.
        $response = $this->postJson(
            "/api/examinations/{$examId}/duty-roster/auto-assign",
            [
                'duty_role_id' => $role->id,
                'required_per_slot' => 1,
                'staff_user_ids' => [$loadedStaff->id, $freeStaff->id],
            ]
        )->assertOk();

        $this->assertEquals(1, $response->json('created'));

        // The free staff member must be selected.
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

        // Another institution's roster history must not be accessible.
        $this->getJson(
            "/api/examinations/{$otherExam->id}/duty-roster/history"
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

    public function test_staff_can_initiate_swap_with_explicit_consent(): void
    {
        $data = $this->prepareSwapScenario('initiate');

        Sanctum::actingAs($data['firstStaff']);

        $response = $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps",
            [
                'first_assignment_id' => $data['firstAssignmentId'],
                'second_assignment_id' => $data['secondAssignmentId'],
                'confirm_consent' => true,
            ]
        )->assertCreated()
            ->assertJsonPath('swap.status', 'pending_target')
            ->assertJsonPath('swap.initiator.id', $data['firstStaff']->id)
            ->assertJsonPath('swap.target_user.id', $data['secondStaff']->id);

        $swapId = $response->json('swap.id');

        $this->assertDatabaseHas('exam_duty_swap_requests', [
            'id' => $swapId,
            'institution_id' => $this->institution->id,
            'roster_id' => ExamDutyRoster::where('exam_id', $data['examId'])
                ->where('institution_id', $this->institution->id)
                ->where('version', 1)->value('id'),
            'first_assignment_id' => $data['firstAssignmentId'],
            'second_assignment_id' => $data['secondAssignmentId'],
            'initiated_by' => $data['firstStaff']->id,
            'target_user_id' => $data['secondStaff']->id,
            'status' => 'pending_target',
        ]);

        $this->assertNotNull(
            DB::table('exam_duty_swap_requests')->where('id', $swapId)->value('initiator_accepted_at')
        );
        $this->assertNull(
            DB::table('exam_duty_swap_requests')->where('id', $swapId)->value('target_accepted_at')
        );
    }

    public function test_target_acceptance_moves_swap_to_pending_officer(): void
    {
        $data = $this->prepareSwapScenario('target-accept');

        Sanctum::actingAs($data['firstStaff']);

        $swapId = $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps",
            [
                'first_assignment_id' => $data['firstAssignmentId'],
                'second_assignment_id' => $data['secondAssignmentId'],
                'confirm_consent' => true,
            ]
        )->assertCreated()->json('swap.id');

        Sanctum::actingAs($data['secondStaff']);

        $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps/{$swapId}/respond",
            ['response' => 'accepted']
        )->assertOk()->assertJsonPath('swap.status', 'pending_officer');

        $this->assertDatabaseHas('exam_duty_swap_requests', [
            'id' => $swapId,
            'status' => 'pending_officer',
        ]);

        $this->assertNotNull(
            DB::table('exam_duty_swap_requests')->where('id', $swapId)->value('initiator_accepted_at')
        );
        $this->assertNotNull(
            DB::table('exam_duty_swap_requests')->where('id', $swapId)->value('target_accepted_at')
        );
        $this->assertNotNull(
            DB::table('exam_duty_swap_requests')->where('id', $swapId)->value('target_responded_at')
        );
    }

    public function test_officer_cannot_approve_swap_before_target_consent(): void
    {
        $data = $this->prepareSwapScenario('no-target-consent');

        Sanctum::actingAs($data['firstStaff']);

        $swapId = $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps",
            [
                'first_assignment_id' => $data['firstAssignmentId'],
                'second_assignment_id' => $data['secondAssignmentId'],
                'confirm_consent' => true,
            ]
        )->assertCreated()->json('swap.id');

        Sanctum::actingAs($data['officer']);

        $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps/{$swapId}/approve"
        )->assertStatus(409);

        $this->assertDatabaseHas('exam_duty_swap_requests', [
            'id' => $swapId,
            'status' => 'pending_target',
        ]);

        $this->assertDatabaseCount('exam_duty_rosters', 1);
    }

    public function test_officer_approval_creates_new_roster_with_swapped_accepted_assignments(): void
    {
        $data = $this->prepareSwapScenario('approve');

        Sanctum::actingAs($data['firstStaff']);

        $swapId = $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps",
            [
                'first_assignment_id' => $data['firstAssignmentId'],
                'second_assignment_id' => $data['secondAssignmentId'],
                'confirm_consent' => true,
            ]
        )->assertCreated()->json('swap.id');

        Sanctum::actingAs($data['secondStaff']);

        $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps/{$swapId}/respond",
            ['response' => 'accepted']
        )->assertOk()->assertJsonPath('swap.status', 'pending_officer');

        Sanctum::actingAs($data['officer']);

        $response = $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps/{$swapId}/approve"
        )->assertOk()
            ->assertJsonPath('swap.status', 'approved')
            ->assertJsonPath('roster.version', 2)
            ->assertJsonPath('roster.status', 'draft');

        $newRosterId = $response->json('roster.id');

        $this->assertDatabaseHas('exam_duty_swap_requests', [
            'id' => $swapId,
            'status' => 'approved',
            'decided_by' => $data['officer']->id,
        ]);

        $this->assertDatabaseHas('exam_duty_assignments', [
            'roster_id' => $newRosterId,
            'exam_subject_id' => $data['entryId'],
            'user_id' => $data['secondStaff']->id,
            'duty_role_id' => $data['roleId'],
            'status' => 'accepted',
        ]);

        $this->assertDatabaseHas('exam_duty_assignments', [
            'roster_id' => $newRosterId,
            'exam_subject_id' => $data['entryId'],
            'user_id' => $data['firstStaff']->id,
            'duty_role_id' => $data['roleId'],
            'status' => 'accepted',
        ]);

        // The published version-1 assignments are historical and must not be mutated.
        $this->assertDatabaseHas('exam_duty_assignments', [
            'id' => $data['firstAssignmentId'],
            'roster_id' => ExamDutyRoster::where('exam_id', $data['examId'])
                ->where('version', 1)->value('id'),
            'user_id' => $data['firstStaff']->id,
            'status' => 'accepted',
        ]);

        $this->assertDatabaseHas('exam_duty_assignments', [
            'id' => $data['secondAssignmentId'],
            'user_id' => $data['secondStaff']->id,
            'status' => 'accepted',
        ]);

        $this->assertDatabaseHas('exam_invigilations', [
            'institution_id' => $this->institution->id,
            'exam_subject_id' => $data['entryId'],
            'user_id' => $data['firstStaff']->id,
            'duty' => 'invigilator',
        ]);

        $this->assertDatabaseHas('exam_invigilations', [
            'institution_id' => $this->institution->id,
            'exam_subject_id' => $data['entryId'],
            'user_id' => $data['secondStaff']->id,
            'duty' => 'invigilator',
        ]);

        $this->assertDatabaseCount('exam_duty_rosters', 2);
        $this->assertDatabaseCount('exam_duty_assignments', 4);
    }

    public function test_target_can_decline_swap_and_decline_is_audited(): void
    {
        $data = $this->prepareSwapScenario('decline');

        Sanctum::actingAs($data['firstStaff']);

        $swapId = $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps",
            [
                'first_assignment_id' => $data['firstAssignmentId'],
                'second_assignment_id' => $data['secondAssignmentId'],
                'confirm_consent' => true,
            ]
        )->assertCreated()->json('swap.id');

        Sanctum::actingAs($data['secondStaff']);

        $reason = 'I cannot take the proposed duty.';
        $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps/{$swapId}/respond",
            ['response' => 'declined', 'reason' => $reason]
        )->assertOk()->assertJsonPath('swap.status', 'rejected');

        $this->assertDatabaseHas('exam_duty_swap_requests', [
            'id' => $swapId,
            'status' => 'rejected',
            'target_response_reason' => $reason,
            'decided_by' => null,
            'decision_reason' => null,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'institution_id' => $this->institution->id,
            'event' => 'exam.duty_swap_declined',
            'subject_type' => 'ExamDutySwapRequest',
            'subject_id' => (string) $swapId,
            'actor_user_id' => $data['secondStaff']->id,
        ]);

        $this->assertDatabaseCount('exam_duty_rosters', 1);
    }

    public function test_officer_can_reject_mutually_accepted_swap_with_reason(): void
    {
        $data = $this->prepareSwapScenario('officer-reject');

        Sanctum::actingAs($data['firstStaff']);

        $swapId = $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps",
            [
                'first_assignment_id' => $data['firstAssignmentId'],
                'second_assignment_id' => $data['secondAssignmentId'],
                'confirm_consent' => true,
            ]
        )->assertCreated()->json('swap.id');

        Sanctum::actingAs($data['secondStaff']);

        $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps/{$swapId}/respond",
            ['response' => 'accepted']
        )->assertOk();

        Sanctum::actingAs($data['officer']);

        $reason = 'The revised allocation creates a scheduling conflict.';
        $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps/{$swapId}/reject",
            ['reason' => $reason]
        )->assertOk()->assertJsonPath('swap.status', 'rejected');

        $this->assertDatabaseHas('exam_duty_swap_requests', [
            'id' => $swapId,
            'status' => 'rejected',
            'decided_by' => $data['officer']->id,
            'decision_reason' => $reason,
        ]);

        $this->assertDatabaseCount('exam_duty_rosters', 1);
    }

    public function test_either_swap_participant_can_withdraw_before_officer_decision(): void
    {
        $data = $this->prepareSwapScenario('withdraw');

        Sanctum::actingAs($data['firstStaff']);

        $swapId = $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps",
            [
                'first_assignment_id' => $data['firstAssignmentId'],
                'second_assignment_id' => $data['secondAssignmentId'],
                'confirm_consent' => true,
            ]
        )->assertCreated()->json('swap.id');

        // The invited participant can withdraw while the request awaits their consent.
        Sanctum::actingAs($data['secondStaff']);

        $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps/{$swapId}/withdraw"
        )->assertOk()->assertJsonPath('swap.status', 'withdrawn');

        $this->assertDatabaseHas('exam_duty_swap_requests', [
            'id' => $swapId,
            'status' => 'withdrawn',
            'withdrawn_by' => $data['secondStaff']->id,
        ]);

        // A withdrawn request cannot be withdrawn again or decided by the officer.
        Sanctum::actingAs($data['officer']);

        $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps/{$swapId}/approve"
        )->assertStatus(409);

        $this->assertDatabaseCount('exam_duty_rosters', 1);
    }

    public function test_assignment_cannot_be_used_in_two_pending_swap_requests(): void
    {
        $data = $this->prepareSwapScenario('overlap');
        $thirdStaff = $this->makeUser('swap-third-overlap@duty.test', $this->facultyRoleId);

        // A published roster is immutable; create version 2 and publish it so that
        // a third assignment can be added before publication.
        Sanctum::actingAs($data['admin']);
        $this->postJson("/api/examinations/{$data['examId']}/duty-roster/new-version")
            ->assertCreated();

        $thirdAssignmentId = $this->postJson(
            "/api/examinations/{$data['examId']}/duty-assignments",
            [
                'exam_subject_id' => $data['entryId'],
                'user_id' => $thirdStaff->id,
                'duty_role_id' => $data['roleId'],
            ]
        )->assertCreated()->json('assignment.id');

        // Re-publish version 2 and have all three staff accept their duties.
        $newOfficer = $this->makeUser('swap-officer-overlap-v2@duty.test', $this->officerRoleId);
        $this->postJson("/api/examinations/{$data['examId']}/duty-roster/submit")->assertOk();
        $this->confirmMfa($newOfficer);
        Sanctum::actingAs($newOfficer);
        $this->postJson("/api/examinations/{$data['examId']}/duty-roster/approve")->assertOk();
        $this->postJson("/api/examinations/{$data['examId']}/duty-roster/publish")->assertOk();

        foreach ([
            [$data['firstStaff'], $data['firstAssignmentId']],
            [$data['secondStaff'], $data['secondAssignmentId']],
            [$thirdStaff, $thirdAssignmentId],
        ] as [$staff, $assignmentId]) {
            // The copied version-2 assignments have new IDs; resolve them by owner.
            $assignment = ExamDutyAssignment::where('roster_id', ExamDutyRoster::where('exam_id', $data['examId'])
                ->where('version', 2)->value('id'))
                ->where('user_id', $staff->id)
                ->firstOrFail();

            Sanctum::actingAs($staff);
            $this->postJson(
                "/api/examinations/{$data['examId']}/duty-assignments/{$assignment->id}/respond",
                ['response' => 'accepted']
            )->assertOk();
        }

        $version2RosterId = ExamDutyRoster::where('exam_id', $data['examId'])
            ->where('version', 2)->value('id');

        $firstV2 = ExamDutyAssignment::where('roster_id', $version2RosterId)
            ->where('user_id', $data['firstStaff']->id)->firstOrFail();
        $secondV2 = ExamDutyAssignment::where('roster_id', $version2RosterId)
            ->where('user_id', $data['secondStaff']->id)->firstOrFail();
        $thirdV2 = ExamDutyAssignment::where('roster_id', $version2RosterId)
            ->where('user_id', $thirdStaff->id)->firstOrFail();

        Sanctum::actingAs($data['firstStaff']);
        $firstSwapId = $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps",
            [
                'first_assignment_id' => $firstV2->id,
                'second_assignment_id' => $secondV2->id,
                'confirm_consent' => true,
            ]
        )->assertCreated()->json('swap.id');

        // The second request reuses the second assignment from the first request.
        Sanctum::actingAs($data['secondStaff']);
        $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps",
            [
                'first_assignment_id' => $secondV2->id,
                'second_assignment_id' => $thirdV2->id,
                'confirm_consent' => true,
            ]
        )->assertStatus(409);

        $this->assertDatabaseHas('exam_duty_swap_requests', [
            'id' => $firstSwapId,
            'status' => 'pending_target',
        ]);
        $this->assertDatabaseCount('exam_duty_swap_requests', 1);
    }

    public function test_swap_approval_is_blocked_if_a_newer_roster_version_exists(): void
    {
        $data = $this->prepareSwapScenario('stale-roster');

        Sanctum::actingAs($data['firstStaff']);

        $swapId = $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps",
            [
                'first_assignment_id' => $data['firstAssignmentId'],
                'second_assignment_id' => $data['secondAssignmentId'],
                'confirm_consent' => true,
            ]
        )->assertCreated()->json('swap.id');

        Sanctum::actingAs($data['secondStaff']);
        $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps/{$swapId}/respond",
            ['response' => 'accepted']
        )->assertOk();

        Sanctum::actingAs($data['admin']);
        $this->postJson("/api/examinations/{$data['examId']}/duty-roster/new-version")
            ->assertCreated();

        Sanctum::actingAs($data['officer']);
        $this->postJson(
            "/api/examinations/{$data['examId']}/duty-swaps/{$swapId}/approve"
        )->assertStatus(409);

        $this->assertDatabaseHas('exam_duty_swap_requests', [
            'id' => $swapId,
            'status' => 'pending_officer',
        ]);

        $this->assertDatabaseCount('exam_duty_rosters', 2);
    }
}
