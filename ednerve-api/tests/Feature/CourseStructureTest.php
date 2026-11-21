<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Programme;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseStructureTest extends TestCase
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
            'code' => 'CSTA',
            'name' => 'Structure Institution A',
            'slug' => 'csta',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        $this->institutionB = Institution::create([
            'code' => 'CSTB',
            'name' => 'Structure Institution B',
            'slug' => 'cstb',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        $this->roleAdminId = Role::create([
            'code' => 'institution_admin',
            'name' => 'Institution Admin',
            'status' => 'active',
        ])->id;

        Role::create([
            'code' => 'faculty',
            'name' => 'Faculty',
            'status' => 'active',
        ]);
    }

    private function makeInstitutionUser(string $email, Institution $institution, bool $isAdmin = true): User
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
            'role_id' => $isAdmin
                ? $this->roleAdminId
                : Role::where('code', 'faculty')->value('id'),
            'academic_unit_id' => null,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function makeSubject(Institution $institution, string $code = 'CS101', string $name = 'Intro'): Subject
    {
        return Subject::create([
            'institution_id' => $institution->id,
            'code' => $code,
            'name' => $name,
            'credits' => 4,
            'subject_type' => 'theory',
            'status' => 'active',
        ]);
    }

    // ----------------------------------------------------------
    // Components
    // ----------------------------------------------------------

    public function test_subject_component_upsert_index_and_delete(): void
    {
        $admin = $this->makeInstitutionUser('admin.a.test', $this->institutionA);
        $subject = $this->makeSubject($this->institutionA);

        Sanctum::actingAs($admin);

        $this->postJson("/api/subjects/{$subject->id}/components", [
            'kind' => 'theory',
            'credits' => 3,
            'hours_per_week' => 4,
            'max_marks' => 70,
            'position' => 1,
        ])->assertCreated();

        $this->postJson("/api/subjects/{$subject->id}/components", [
            'kind' => 'practical',
            'credits' => 1,
            'hours_per_week' => 2,
            'position' => 2,
        ])->assertCreated();

        $this->getJson("/api/subjects/{$subject->id}/components")
            ->assertOk()
            ->assertJsonFragment(['kind' => 'theory', 'hours_per_week' => 4])
            ->assertJsonFragment(['kind' => 'practical', 'hours_per_week' => 2]);

        // re-upsert theory: updates in place instead of duplicating
        $this->postJson("/api/subjects/{$subject->id}/components", [
            'kind' => 'theory',
            'credits' => 3,
            'hours_per_week' => 5,
        ])->assertCreated();

        $this->assertDatabaseCount('subject_components', 2);

        $this->deleteJson("/api/subjects/{$subject->id}/components/practical")
            ->assertOk();

        $this->assertDatabaseMissing('subject_components', ['kind' => 'practical']);
    }

    public function test_faculty_cannot_mutate_components(): void
    {
        $faculty = $this->makeInstitutionUser('fac.a.test', $this->institutionA, false);
        $subject = $this->makeSubject($this->institutionA);

        Sanctum::actingAs($faculty);

        $this->postJson("/api/subjects/{$subject->id}/components", [
            'kind' => 'theory',
        ])->assertForbidden();
    }

    // ----------------------------------------------------------
    // Prerequisites
    // ----------------------------------------------------------

    public function test_prerequisites_crud_and_self_prerequisite_rejected(): void
    {
        $admin = $this->makeInstitutionUser('admin.a.test', $this->institutionA);
        $subject = $this->makeSubject($this->institutionA, 'CS101');
        $prereq = $this->makeSubject($this->institutionA, 'CS100', 'Discrete Math');

        Sanctum::actingAs($admin);

        $this->postJson("/api/subjects/{$subject->id}/prerequisites", [
            'prerequisite_subject_id' => $prereq->id,
        ])->assertCreated();

        $this->getJson("/api/subjects/{$subject->id}/prerequisites")
            ->assertOk()
            ->assertJsonFragment(['code' => 'CS100']);

        $this->postJson("/api/subjects/{$subject->id}/prerequisites", [
            'prerequisite_subject_id' => $subject->id,
        ])->assertStatus(422);

        $this->assertDatabaseHas('subject_prerequisites', [
            'institution_id' => $this->institutionA->id,
            'subject_id' => $subject->id,
            'prerequisite_subject_id' => $prereq->id,
        ]);
    }

    public function test_prerequisite_cycle_is_rejected(): void
    {
        $admin = $this->makeInstitutionUser('admin.a.test', $this->institutionA);
        $a = $this->makeSubject($this->institutionA, 'CS A');
        $b = $this->makeSubject($this->institutionA, 'CS B');
        $c = $this->makeSubject($this->institutionA, 'CS C');

        Sanctum::actingAs($admin);

        // A -> B -> C is fine
        $this->postJson("/api/subjects/{$a->id}/prerequisites", ['prerequisite_subject_id' => $b->id])->assertCreated();
        $this->postJson("/api/subjects/{$b->id}/prerequisites", ['prerequisite_subject_id' => $c->id])->assertCreated();

        // C -> A would close the cycle
        $this->postJson("/api/subjects/{$c->id}/prerequisites", ['prerequisite_subject_id' => $a->id])
            ->assertStatus(422);
    }

    public function test_prerequisite_from_other_institution_is_rejected(): void
    {
        $admin = $this->makeInstitutionUser('admin.a.test', $this->institutionA);
        $subject = $this->makeSubject($this->institutionA, 'CS101');
        $foreign = $this->makeSubject($this->institutionB, 'CS999', 'Foreign');

        Sanctum::actingAs($admin);

        $this->postJson("/api/subjects/{$subject->id}/prerequisites", [
            'prerequisite_subject_id' => $foreign->id,
        ])->assertNotFound();
    }

    // ----------------------------------------------------------
    // Elective groups
    // ----------------------------------------------------------

    public function test_elective_group_lifecycle_and_subject_attach(): void
    {
        $admin = $this->makeInstitutionUser('admin.a.test', $this->institutionA);
        $programme = Programme::create([
            'institution_id' => $this->institutionA->id,
            'code' => 'BCA',
            'name' => 'Bachelor of Computer Applications',
            'level' => 'UG',
            'duration_semesters' => 6,
            'status' => 'active',
        ]);
        $subject = $this->makeSubject($this->institutionA, 'CS150', 'Open Elective');

        Sanctum::actingAs($admin);

        $groupResponse = $this->postJson('/api/elective-groups', [
            'programme_id' => $programme->id,
            'semester' => 3,
            'code' => 'OPEN',
            'name' => 'Open Electives (choose 1)',
            'capacity' => 120,
            'required_choices' => 1,
        ]);
        $groupResponse->assertCreated();
        $groupId = $groupResponse->json('group.id');

        $this->postJson("/api/elective-groups/{$groupId}/subjects", [
            'subject_id' => $subject->id,
        ])->assertCreated();

        // attaching the same subject twice is idempotent
        $this->postJson("/api/elective-groups/{$groupId}/subjects", [
            'subject_id' => $subject->id,
        ])->assertOk();

        $this->getJson('/api/elective-groups?programme_id=' . $programme->id)
            ->assertOk()
            ->assertJsonFragment(['code' => 'OPEN'])
            ->assertJsonFragment(['subject_id' => $subject->id]);

        $this->deleteJson("/api/elective-groups/{$groupId}/subjects/{$subject->id}")
            ->assertOk();

        $this->deleteJson("/api/elective-groups/{$groupId}")->assertOk();

        $this->assertDatabaseHas('elective_groups', ['id' => $groupId, 'status' => 'inactive']);
    }

    public function test_elective_group_duplicate_code_per_programme_semester_rejected(): void
    {
        $admin = $this->makeInstitutionUser('admin.a.test', $this->institutionA);
        $programme = Programme::create([
            'institution_id' => $this->institutionA->id,
            'code' => 'MCA',
            'name' => 'Master of Computer Applications',
            'level' => 'PG',
            'duration_semesters' => 4,
            'status' => 'active',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/elective-groups', [
            'programme_id' => $programme->id,
            'semester' => 2,
            'code' => 'DSA',
            'name' => 'Data Science Electives',
        ])->assertCreated();

        $this->postJson('/api/elective-groups', [
            'programme_id' => $programme->id,
            'semester' => 2,
            'code' => 'DSA',
            'name' => 'Duplicate group',
        ])->assertStatus(422);
    }
}
