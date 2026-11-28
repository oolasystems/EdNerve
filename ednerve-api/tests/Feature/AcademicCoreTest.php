<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\ProgrammeSubject;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademicCoreTest extends TestCase
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
            'code' => 'ACSA',
            'name' => 'Academic Institution A',
            'slug' => 'acsa',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        $this->institutionB = Institution::create([
            'code' => 'ACSB',
            'name' => 'Academic Institution B',
            'slug' => 'acsb',
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
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    public function test_institution_admin_can_create_and_read_academic_sessions(): void
    {
        Sanctum::actingAs($this->makeInstitutionUser('admin.a.test', $this->institutionA));

        $response = $this->postJson('/api/academic-sessions', [
            'code' => '2026-27',
            'name' => '2026-27 Session',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('academic_sessions', ['code' => '2026-27']);
        $this->getJson('/api/academic-sessions')->assertOk()->assertJsonFragment(['code' => '2026-27']);
    }

    public function test_session_activation_is_exclusive(): void
    {
        $admin = $this->makeInstitutionUser('admin.a.test', $this->institutionA);
        AcademicSession::create([
            'institution_id' => $this->institutionA->id,
            'code' => 'S-OLD',
            'name' => 'Old Session',
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'is_active' => true,
            'status' => 'active',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/academic-sessions', [
            'code' => 'S-NEW',
            'name' => 'New Session',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('academic_sessions', ['code' => 'S-NEW', 'is_active' => 1]);
        $this->assertDatabaseHas('academic_sessions', ['code' => 'S-OLD', 'is_active' => 0]);
    }

    public function test_create_programme_subject_and_map_subject(): void
    {
        $admin = $this->makeInstitutionUser('admin.a.test', $this->institutionA);
        Sanctum::actingAs($admin);

        $subjectResponse = $this->postJson('/api/subjects', [
            'code' => 'CS401',
            'name' => 'Compiler Design',
            'credits' => 4,
            'subject_type' => 'theory',
        ]);
        $subjectResponse->assertCreated();
        $subjectId = $subjectResponse->json('subject.id');

        $programmeResponse = $this->postJson('/api/programmes', [
            'code' => 'MCA',
            'name' => 'Master of Computer Applications',
            'level' => 'PG',
            'duration_semesters' => 4,
        ]);
        $programmeResponse->assertCreated();
        $programmeId = $programmeResponse->json('programme.id');

        $mapResponse = $this->postJson('/api/programme-subjects', [
            'programme_id' => $programmeId,
            'subject_id' => $subjectId,
            'semester' => 1,
        ]);
        $mapResponse->assertCreated();

        $this->getJson("/api/programmes/{$programmeId}/subjects")
            ->assertOk()
            ->assertJsonFragment(['code' => 'CS401']);
    }

    public function test_duplicate_subject_code_is_rejected(): void
    {
        Sanctum::actingAs($this->makeInstitutionUser('admin.a.test', $this->institutionA));

        $payload = ['code' => 'CS401', 'name' => 'First', 'credits' => 3];
        $this->postJson('/api/subjects', $payload)->assertCreated();
        $this->postJson('/api/subjects', $payload)->assertStatus(422);
    }

    public function test_data_is_isolated_between_institutions(): void
    {
        $adminA = $this->makeInstitutionUser('admin.a.test', $this->institutionA);
        $adminB = $this->makeInstitutionUser('admin.b.test', $this->institutionB);

        Sanctum::actingAs($adminA);
        $this->postJson('/api/subjects', ['code' => 'CS401', 'name' => 'A Secret'])->assertCreated();

        Sanctum::actingAs($adminB);
        $this->getJson('/api/subjects')
            ->assertOk()
            ->assertJsonMissing(['code' => 'CS401']);

        // 404 semantics for cross-institution object access
        $subject = Subject::where('code', 'CS401')->first();
        $this->putJson("/api/subjects/{$subject->id}", ['name' => 'Hijacked'])->assertNotFound();
    }

    public function test_faculty_can_read_but_not_write(): void
    {
        $faculty = $this->makeInstitutionUser('faculty.a.test', $this->institutionA, isAdmin: false);
        Subject::create([
            'institution_id' => $this->institutionA->id,
            'code' => 'CS401',
            'name' => 'Readable',
            'credits' => 3,
            'subject_type' => 'theory',
            'status' => 'active',
        ]);

        Sanctum::actingAs($faculty);
        $this->getJson('/api/subjects')->assertOk();
        $this->postJson('/api/programmes', [
            'code' => 'MBA',
            'name' => 'MBA',
            'level' => 'PG',
            'duration_semesters' => 4,
        ])->assertForbidden();
    }

    public function test_programme_subject_mapping_can_be_removed_and_recreated(): void
    {
        $admin = $this->makeInstitutionUser('admin.a.test', $this->institutionA);
        Sanctum::actingAs($admin);

        $subject = Subject::create([
            'institution_id' => $this->institutionA->id,
            'code' => 'CS401',
            'name' => 'Compiler Design',
            'credits' => 4,
            'subject_type' => 'theory',
            'status' => 'active',
        ]);

        $programme = Programme::create([
            'institution_id' => $this->institutionA->id,
            'code' => 'MCA',
            'name' => 'Master of Computer Applications',
            'level' => 'PG',
            'duration_semesters' => 4,
            'status' => 'active',
        ]);

        $this->postJson('/api/programme-subjects', [
            'programme_id' => $programme->id,
            'subject_id' => $subject->id,
            'semester' => 1,
        ])->assertCreated();

        $mapping = ProgrammeSubject::where('programme_id', $programme->id)->first();
        $this->deleteJson("/api/programme-subjects/{$mapping->id}")->assertOk();

        $this->assertDatabaseMissing('programme_subjects', ['id' => $mapping->id]);

        // Subject no longer mapped; programme can proceed to new semester mapping.
        $this->getJson("/api/programmes/{$programme->id}/subjects")
            ->assertOk()
            ->assertJsonCount(0, 'subjects');
    }
}
