<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Institution;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SyllabusTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'StrongPass@123';

    private Institution $institutionA;
    private Institution $institutionB;
    private int $roleAdminId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institutionA = Institution::create([
            'code' => 'SYSA',
            'name' => 'Syllabus Institution A',
            'slug' => 'sysa',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        $this->institutionB = Institution::create([
            'code' => 'SYSB',
            'name' => 'Syllabus Institution B',
            'slug' => 'sysb',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        $this->roleAdminId = Role::create([
            'code' => 'institution_admin',
            'name' => 'Institution Admin',
            'status' => 'active',
        ])->id;
    }

    private function makeInstitutionUser(string $email, Institution $institution): User
    {
        $user = User::create([
            'account_type' => 'institution',
            'institution_id' => $institution->id,
            'name' => $email,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'status' => 'active',
        ]);

        \DB::table('user_role_assignments')->insert([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'role_id' => $this->roleAdminId,
            'academic_unit_id' => null,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function makeSubject(Institution $institution): Subject
    {
        return Subject::create([
            'institution_id' => $institution->id,
            'code' => 'CS301',
            'name' => 'Database Systems',
            'credits' => 4,
            'subject_type' => 'theory',
            'status' => 'active',
        ]);
    }

    private function makeSession(Institution $institution, string $code = '2026-27'): AcademicSession
    {
        return AcademicSession::create([
            'institution_id' => $institution->id,
            'code' => $code,
            'name' => $code . ' Session',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    public function test_draft_create_update_and_units_roundtrip(): void
    {
        $admin = $this->makeInstitutionUser('author.a.test', $this->institutionA);
        $subject = $this->makeSubject($this->institutionA);
        $session = $this->makeSession($this->institutionA);

        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/syllabi', [
            'subject_id' => $subject->id,
            'academic_session_id' => $session->id,
            'version' => '2026.1',
            'title' => 'CS301 Syllabus 2026.1',
            'units' => [
                ['unit_number' => '1', 'title' => 'ER Modelling', 'topics' => "## Topics\n- ER diagrams", 'learning_outcomes' => 'Model schemas', 'prescribed_hours' => 12, 'position' => 1],
                ['unit_number' => '2', 'title' => 'Normalisation', 'topics' => '1NF to BCNF', 'prescribed_hours' => 10, 'position' => 2],
            ],
        ]);

        $create->assertCreated();
        $id = $create->json('syllabus.id');

        $this->getJson("/api/syllabi/{$id}")
            ->assertOk()
            ->assertJsonFragment(['version' => '2026.1', 'status' => 'draft'])
            ->assertJsonFragment(['unit_number' => '1', 'title' => 'ER Modelling']);

        // replace units on update
        $this->putJson("/api/syllabi/{$id}", [
            'title' => 'CS301 Syllabus 2026.1 (rev)',
            'units' => [
                ['unit_number' => '1', 'title' => 'Revised Unit 1', 'prescribed_hours' => 14, 'position' => 1],
            ],
        ])->assertOk();

        $this->assertDatabaseMissing('syllabus_units', ['unit_number' => '2']);
        $this->assertDatabaseHas('syllabus_units', ['unit_number' => '1', 'title' => 'Revised Unit 1', 'prescribed_hours' => 14]);
    }

    public function test_publish_lifecycle_author_cannot_approve_and_supersede_works(): void
    {
        $author = $this->makeInstitutionUser('author.a.test', $this->institutionA);
        $approver = $this->makeInstitutionUser('approver.a.test', $this->institutionA);
        $subject = $this->makeSubject($this->institutionA);
        $session = $this->makeSession($this->institutionA);

        Sanctum::actingAs($author);

        $create = $this->postJson('/api/syllabi', [
            'subject_id' => $subject->id,
            'academic_session_id' => $session->id,
            'version' => '2026.1',
            'title' => 'First version',
            'units' => [
                ['unit_number' => '1', 'title' => 'Intro', 'position' => 1],
            ],
        ]);
        $id = $create->json('syllabus.id');

        // cannot approve from draft
        $this->postJson("/api/syllabi/{$id}/approve")->assertStatus(422);

        // submit for review
        $this->postJson("/api/syllabi/{$id}/submit")->assertOk();

        // author cannot approve own syllabus (SoD)
        $this->postJson("/api/syllabi/{$id}/approve")->assertForbidden();

        // approver approves and publishes
        Sanctum::actingAs($approver);
        $this->postJson("/api/syllabi/{$id}/approve")->assertOk();
        $this->postJson("/api/syllabi/{$id}/publish")->assertOk();

        $this->assertDatabaseHas('syllabi', ['id' => $id, 'status' => 'published']);

        // published syllabus is immutable
        $this->putJson("/api/syllabi/{$id}", ['title' => 'Hack'])->assertStatus(422);

        // author clones -> new draft, increments version suffix
        Sanctum::actingAs($author);
        $clone = $this->postJson("/api/syllabi/{$id}/clone", []);
        $clone->assertCreated();
        $cloneId = $clone->json('syllabus.id');

        Sanctum::actingAs($approver);
        $this->postJson("/api/syllabi/{$cloneId}/submit")->assertOk();
        $this->postJson("/api/syllabi/{$cloneId}/approve")->assertOk();
        $this->postJson("/api/syllabi/{$cloneId}/publish")->assertOk();

        $this->assertDatabaseHas('syllabi', ['id' => $id, 'status' => 'archived', 'superseded_by' => $cloneId]);
        $this->assertDatabaseHas('syllabi', ['id' => $cloneId, 'status' => 'published']);
    }

    public function test_duplicate_version_per_subject_rejected(): void
    {
        $admin = $this->makeInstitutionUser('admin.a.test', $this->institutionA);
        $subject = $this->makeSubject($this->institutionA);

        Sanctum::actingAs($admin);

        $this->postJson('/api/syllabi', [
            'subject_id' => $subject->id,
            'version' => '2026.1',
            'title' => 'First',
        ])->assertCreated();

        $this->postJson('/api/syllabi', [
            'subject_id' => $subject->id,
            'version' => '2026.1',
            'title' => 'Duplicate',
        ])->assertStatus(422);
    }

    public function test_syllabus_from_other_institution_not_visible(): void
    {
        $adminA = $this->makeInstitutionUser('admin.a.test', $this->institutionA);
        $adminB = $this->makeInstitutionUser('admin.b.test', $this->institutionB);
        $subject = $this->makeSubject($this->institutionA);

        Sanctum::actingAs($adminA);
        $create = $this->postJson('/api/syllabi', [
            'subject_id' => $subject->id,
            'version' => '2026.1',
            'title' => 'Private draft',
        ]);
        $id = $create->json('syllabus.id');

        Sanctum::actingAs($adminB);
        $this->getJson("/api/syllabi/{$id}")->assertNotFound();
    }

    public function test_draft_can_be_archived_and_published_cannot_be_archived(): void
    {
        $author = $this->makeInstitutionUser('author.a.test', $this->institutionA);
        $approver = $this->makeInstitutionUser('approver.a.test', $this->institutionA);
        $subject = $this->makeSubject($this->institutionA);

        Sanctum::actingAs($author);
        $create = $this->postJson('/api/syllabi', [
            'subject_id' => $subject->id,
            'version' => '2026.1',
            'title' => 'Doc',
        ]);
        $id = $create->json('syllabus.id');

        // archive a draft
        $this->deleteJson("/api/syllabi/{$id}")->assertOk();

        // clone the archived one and push to published, then try to archive
        $clone = $this->postJson("/api/syllabi/{$id}/clone", ['version' => '2026.2']);
        $cloneId = $clone->json('syllabus.id');

        Sanctum::actingAs($approver);
        $this->postJson("/api/syllabi/{$cloneId}/submit")->assertOk();
        $this->postJson("/api/syllabi/{$cloneId}/approve")->assertOk();
        $this->postJson("/api/syllabi/{$cloneId}/publish")->assertOk();
        $this->deleteJson("/api/syllabi/{$cloneId}")->assertStatus(422);
    }
}
