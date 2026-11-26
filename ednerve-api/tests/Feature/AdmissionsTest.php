<?php

namespace Tests\Feature;

use App\Models\AdmissionIntake;
use App\Models\AcademicSession;
use App\Models\Application;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdmissionsTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'StrongPass@123';

    private Institution $institutionA;
    private Institution $institutionB;
    private Programme $programme;
    private AcademicSession $session;
    private int $roleAdminId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institutionA = Institution::create([
            'code' => 'ADMA',
            'name' => 'Admission Institution A',
            'slug' => 'adma',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        $this->institutionB = Institution::create([
            'code' => 'ADMB',
            'name' => 'Admission Institution B',
            'slug' => 'admb',
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
            'code' => 'student',
            'name' => 'Student',
            'status' => 'active',
        ]);

        $this->session = AcademicSession::create([
            'institution_id' => $this->institutionA->id,
            'code' => '2026-27',
            'name' => '2026-27 Session',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->programme = Programme::create([
            'institution_id' => $this->institutionA->id,
            'code' => 'BTCSE',
            'name' => 'B.Tech CSE',
            'level' => 'UG',
            'duration_semesters' => 8,
            'status' => 'active',
        ]);
    }

    private function makeInstitutionUser(
        string $email,
        Institution $institution,
        bool $isAdmin = true
    ): User {
        $user = User::create([
            'account_type' => 'institution',
            'institution_id' => $institution->id,
            'name' => $email,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'status' => 'active',
        ]);

        DB::table('user_role_assignments')->insert([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'role_id' => $isAdmin
                ? $this->roleAdminId
                : Role::where('code', 'student')->value('id'),
            'academic_unit_id' => null,
            'is_primary' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function openIntake(Institution $institution, Programme $programme, AcademicSession $session): AdmissionIntake
    {
        return AdmissionIntake::create([
            'institution_id' => $institution->id,
            'programme_id' => $programme->id,
            'academic_session_id' => $session->id,
            'name' => '2026 BTech CSE Intake',
            'open_date' => now()->subDay()->toDateString(),
            'close_date' => now()->addDays(30)->toDateString(),
            'seat_capacity' => 60,
            'status' => 'open',
            'application_fee' => 1500,
        ]);
    }

    private function applicationPayload(int $intakeId): array
    {
        return [
            'intake_id' => $intakeId,
            'first_name' => 'Riya',
            'last_name' => 'Sharma',
            'dob' => '2007-04-12',
            'gender' => 'Female',
            'email' => 'riya@example.test',
            'phone' => '9876543211',
            'last_exam_name' => '12th',
            'last_exam_board_or_univ' => 'CBSE',
            'last_exam_year' => 2025,
            'last_exam_percentage' => 92.5,
        ];
    }

    public function test_public_applicant_can_submit_and_track_application(): void
    {
        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);

        $submit = $this->postJson('/api/public/applications', $this->applicationPayload($intake->id));
        $submit->assertCreated()->assertJsonStructure(['application_number', 'status']);

        $number = $submit->json('application_number');

        $status = $this->getJson('/api/public/application-status?' . http_build_query([
            'application_number' => $number,
            'dob' => '2007-04-12',
        ]));
        $status->assertOk()
            ->assertJsonFragment(['application_number' => $number, 'status' => 'new']);
    }

    public function test_closed_intake_rejects_submission(): void
    {
        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);
        $intake->update(['status' => 'closed']);

        $this->postJson('/api/public/applications', $this->applicationPayload($intake->id))
            ->assertStatus(422);
    }

    public function test_wrong_dob_does_not_reveal_application(): void
    {
        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);

        $submit = $this->postJson('/api/public/applications', $this->applicationPayload($intake->id));
        $number = $submit->json('application_number');

        $this->getJson('/api/public/application-status?' . http_build_query([
            'application_number' => $number,
            'dob' => '2000-01-01',
        ]))->assertNotFound();
    }

    public function test_admin_reviews_and_enrolls_admitted_applicant(): void
    {
        $admin = $this->makeInstitutionUser('admin.a@test', $this->institutionA);
        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);
        $number = $this->postJson('/api/public/applications', $this->applicationPayload($intake->id))
            ->json('application_number');

        Sanctum::actingAs($admin);

        $applicationId = Application::where('application_number', $number)->value('id');

        $this->patchJson("/api/admissions/applications/{$applicationId}/decision", ['status' => 'admitted'])
            ->assertOk();

        $enroll = $this->postJson("/api/admissions/applications/{$applicationId}/enroll");
        $enroll->assertCreated()
            ->assertJsonMissingPath('credentials')
            ->assertJsonMissingPath('credentials.temporary_password');

        $this->assertDatabaseHas('users', [
            'email' => 'riya@example.test',
            'institution_id' => $this->institutionA->id,
            'status' => 'pending_activation',
        ]);

        $this->assertDatabaseHas('applications', [
            'id' => $applicationId,
            'status' => 'enrolled',
        ]);
        $this->assertDatabaseHas('users', ['email' => 'riya@example.test', 'institution_id' => $this->institutionA->id]);
    }

    public function test_faculty_cannot_review_or_enroll(): void
    {
        $faculty = $this->makeInstitutionUser('faculty.a@test', $this->institutionA, isAdmin: false);
        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);
        $number = $this->postJson('/api/public/applications', $this->applicationPayload($intake->id))
            ->json('application_number');
        $applicationId = Application::where('application_number', $number)->value('id');

        Sanctum::actingAs($faculty);

        $this->patchJson("/api/admissions/applications/{$applicationId}/decision", ['status' => 'shortlisted'])
            ->assertForbidden();
        $this->postJson("/api/admissions/applications/{$applicationId}/enroll")
            ->assertForbidden();
    }

    public function test_enrollment_is_blocked_by_email_conflict_with_foreign_accounts(): void
    {
        $admin = $this->makeInstitutionUser('admin.a@test', $this->institutionA);
        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);
        $number = $this->postJson('/api/public/applications', $this->applicationPayload($intake->id))
            ->json('application_number');
        $applicationId = Application::where('application_number', $number)->value('id');

        // A platform account already owns riya@example.test
        User::create([
            'account_type' => 'platform',
            'institution_id' => null,
            'name' => 'Platform Root',
            'email' => 'riya@example.test',
            'password' => Hash::make(self::PASSWORD),
            'status' => 'active',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admissions/applications/{$applicationId}/decision", ['status' => 'admitted'])
            ->assertOk();

        // Must be rejected because the email belongs to a non-institution account.
        $this->postJson("/api/admissions/applications/{$applicationId}/enroll")
            ->assertStatus(422);

        // Remove the platform account → enrollment succeeds.
        User::where('email', 'riya@example.test')->whereNull('institution_id')->delete();

        $this->postJson("/api/admissions/applications/{$applicationId}/enroll")
            ->assertCreated();
    }

    public function test_enrollment_is_blocked_when_seat_capacity_is_full(): void
    {
        $admin = $this->makeInstitutionUser('admin.a@test', $this->institutionA);
        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);
        $intake->update(['seat_capacity' => 2]);

        $ids = [];
        for ($i = 1; $i <= 3; $i++) {
            $number = $this->postJson('/api/public/applications', [
                ...$this->applicationPayload($intake->id),
                'email' => "student{$i}@example.test",
            ])->json('application_number');

            Sanctum::actingAs($admin);
            $applicationId = Application::where('application_number', $number)->value('id');

            $this->patchJson("/api/admissions/applications/{$applicationId}/decision", ['status' => 'admitted'])
                ->assertOk();

            $ids[] = $applicationId;
        }

        // First two enrolled succeed
        $this->postJson("/api/admissions/applications/{$ids[0]}/enroll")->assertCreated();
        $this->postJson("/api/admissions/applications/{$ids[1]}/enroll")->assertCreated();

        // Third hits the seat limit
        $response = $this->postJson("/api/admissions/applications/{$ids[2]}/enroll");
        $response->assertStatus(422);
        $this->assertStringContainsString('seat capacity', $response->json('message'));
    }

    public function test_applications_are_isolated_per_institution(): void
    {
        $adminA = $this->makeInstitutionUser('admin.a@test', $this->institutionA);
        $adminB = $this->makeInstitutionUser('admin.b@test', $this->institutionB);

        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);
        $number = $this->postJson('/api/public/applications', $this->applicationPayload($intake->id))
            ->json('application_number');

        Sanctum::actingAs($adminB);

        $applicationId = Application::where('application_number', $number)->value('id');
        $this->getJson("/api/admissions/applications/{$applicationId}")->assertNotFound();
        $this->patchJson("/api/admissions/applications/{$applicationId}/decision", ['status' => 'shortlisted'])
            ->assertNotFound();
    }
}
