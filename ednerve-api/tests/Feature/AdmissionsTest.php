<?php

namespace Tests\Feature;

use App\Models\AdmissionApplicant;
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

    private function createTestApplication(AdmissionIntake $intake, array $payload): Application
    {
        $sequence = Application::where('institution_id', $intake->institution_id)->count() + 1;
        $application = Application::create([
            'institution_id' => $intake->institution_id,
            'intake_id' => $intake->id,
            'first_name' => $payload['first_name'],
            'middle_name' => $payload['middle_name'] ?? null,
            'last_name' => $payload['last_name'],
            'father_name' => $payload['father_name'] ?? null,
            'mother_name' => $payload['mother_name'] ?? null,
            'dob' => $payload['dob'],
            'gender' => $payload['gender'] ?? null,
            'category' => $payload['category'] ?? 'General',
            'nationality' => $payload['nationality'] ?? 'Indian',
            'email' => $payload['email'],
            'phone' => $payload['phone'],
            'address_line' => $payload['address_line'] ?? null,
            'city' => $payload['city'] ?? null,
            'state' => $payload['state'] ?? null,
            'pin_code' => $payload['pin_code'] ?? null,
            'last_exam_name' => $payload['last_exam_name'] ?? null,
            'last_exam_board_or_univ' => $payload['last_exam_board_or_univ'] ?? null,
            'last_exam_year' => $payload['last_exam_year'] ?? null,
            'last_exam_percentage' => $payload['last_exam_percentage'] ?? null,
            'application_number' => strtoupper($this->institutionCodeFor($intake->institution_id)) . '-' . now()->format('Y') . '-' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'status' => 'new',
        ]);

        DB::table('admission_application_events')->insert([
            'institution_id' => $intake->institution_id,
            'application_id' => $application->id,
            'event_type' => 'application_submitted',
            'to_status' => 'new',
            'actor_type' => 'applicant',
            'metadata' => json_encode(['declaration_accepted' => true]),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $application;
    }

    private function institutionCodeFor(int $institutionId): string
    {
        return (string) Institution::whereKey($institutionId)->value('code');
    }

    public function test_anonymous_application_submission_is_retired_and_tracking_is_read_only(): void
    {
        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);

        $this->postJson('/api/public/applications', $this->applicationPayload($intake->id))
            ->assertStatus(410)
            ->assertJsonPath('code', 'ACCOUNT_REQUIRED');

        $application = $this->createTestApplication($intake, $this->applicationPayload($intake->id));

        $this->postJson('/api/public/admissions/adma/application-status', [
            'application_number' => $application->application_number,
            'dob' => '2007-04-12',
        ])->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('application_number', $application->application_number)
            ->assertJsonPath('institution', $this->institutionA->name)
            ->assertJsonMissingPath('email')
            ->assertJsonMissingPath('phone')
            ->assertJsonMissingPath('dob')
            ->assertJsonMissingPath('review_notes');

        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'new']);
    }

    public function test_closed_intake_does_not_enable_anonymous_submission(): void
    {
        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);
        $intake->update(['status' => 'closed']);

        $this->postJson('/api/public/applications', $this->applicationPayload($intake->id))
            ->assertStatus(410)
            ->assertJsonPath('code', 'ACCOUNT_REQUIRED');
    }

    public function test_public_tracking_requires_matching_institution_and_date_of_birth(): void
    {
        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);
        $application = $this->createTestApplication($intake, $this->applicationPayload($intake->id));

        $this->postJson('/api/public/admissions/adma/application-status', [
            'application_number' => $application->application_number,
            'dob' => '2000-01-01',
        ])->assertNotFound();

        $this->postJson('/api/public/admissions/admb/application-status', [
            'application_number' => $application->application_number,
            'dob' => '2007-04-12',
        ])->assertNotFound();
    }

    public function test_admin_reviews_and_enrolls_admitted_applicant(): void
    {
        $admin = $this->makeInstitutionUser('admin.a@test', $this->institutionA);
        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);
        $number = $this->createTestApplication($intake, $this->applicationPayload($intake->id))->application_number;

        Sanctum::actingAs($admin);

        $applicationId = Application::where('application_number', $number)->value('id');

        $this->patchJson("/api/admissions/applications/{$applicationId}/decision", ['status' => 'admitted'])
            ->assertOk();
        $this->assertDatabaseHas('admission_application_events', [
            'application_id' => $applicationId,
            'event_type' => 'admission_decision_recorded',
            'from_status' => 'new',
            'to_status' => 'admitted',
        ]);

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
        $this->assertDatabaseHas('admission_application_events', [
            'application_id' => $applicationId,
            'event_type' => 'applicant_enrolled',
            'from_status' => 'admitted',
            'to_status' => 'enrolled',
        ]);
        $this->assertDatabaseHas('users', ['email' => 'riya@example.test', 'institution_id' => $this->institutionA->id]);
    }

    public function test_faculty_cannot_review_or_enroll(): void
    {
        $faculty = $this->makeInstitutionUser('faculty.a@test', $this->institutionA, isAdmin: false);
        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);
        $number = $this->createTestApplication($intake, $this->applicationPayload($intake->id))->application_number;
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
        $number = $this->createTestApplication($intake, $this->applicationPayload($intake->id))->application_number;
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
        $this->assertDatabaseHas('admission_application_events', [
            'application_id' => $applicationId,
            'event_type' => 'applicant_enrolled',
            'from_status' => 'admitted',
            'to_status' => 'enrolled',
        ]);
    }

    public function test_enrollment_is_blocked_when_seat_capacity_is_full(): void
    {
        $admin = $this->makeInstitutionUser('admin.a@test', $this->institutionA);
        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);
        $intake->update(['seat_capacity' => 2]);

        $ids = [];
        for ($i = 1; $i <= 3; $i++) {
            $number = $this->createTestApplication($intake, [
                ...$this->applicationPayload($intake->id),
                'email' => "student{$i}@example.test",
            ])->application_number;

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

    public function test_application_review_includes_qualifications_documents_and_submission_events(): void
    {
        $admin = $this->makeInstitutionUser('review.details@test', $this->institutionA);
        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);
        $number = $this->createTestApplication($intake, $this->applicationPayload($intake->id))->application_number;
        $application = Application::where('application_number', $number)->firstOrFail();

        DB::table('admission_application_qualifications')->insert([
            'institution_id' => $this->institutionA->id,
            'application_id' => $application->id,
            'qualification_level' => 'higher_secondary',
            'qualification_name' => 'Class XII',
            'board_or_university' => 'CBSE',
            'passing_year' => 2025,
            'percentage' => 92.50,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $templateId = DB::table('admission_form_templates')->insertGetId([
            'institution_id' => $this->institutionA->id,
            'code' => 'REVIEW-DETAILS',
            'name' => 'Review Details Form',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $versionId = DB::table('admission_form_versions')->insertGetId([
            'institution_id' => $this->institutionA->id,
            'template_id' => $templateId,
            'version_number' => 1,
            'status' => 'published',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sectionId = DB::table('admission_form_sections')->insertGetId([
            'institution_id' => $this->institutionA->id,
            'form_version_id' => $versionId,
            'section_key' => 'personal_extra',
            'title' => 'Additional Personal Details',
            'sort_order' => 1,
            'is_visible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('admission_form_fields')->insert([
            'institution_id' => $this->institutionA->id,
            'section_id' => $sectionId,
            'field_key' => 'preferred_language',
            'label' => 'Preferred Language',
            'field_type' => 'text',
            'is_visible' => true,
            'is_applicant_editable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('admission_application_answers')->insert([
            'institution_id' => $this->institutionA->id,
            'application_id' => $application->id,
            'form_version_id' => $versionId,
            'field_key' => 'preferred_language',
            'answer_value' => json_encode('Hindi'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $application->update(['form_version_id' => $versionId]);

        DB::table('admission_application_events')->insert([
            'institution_id' => $this->institutionA->id,
            'application_id' => $application->id,
            'event_type' => 'application_submitted',
            'from_status' => 'draft',
            'to_status' => 'under_review',
            'remarks' => 'Applicant submitted the application.',
            'actor_type' => 'applicant',
            'actor_id' => null,
            'metadata' => json_encode(['declaration_accepted' => true]),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin);
        $response = $this->getJson("/api/admissions/applications/{$application->id}");
        $response->assertOk()
            ->assertJsonPath('review.qualifications.0.qualification_name', 'Class XII')
            ->assertJsonPath('review.form_sections.0.fields.0.answer', 'Hindi')
            ->assertJsonPath('review.form_sections.0.fields.0.label', 'Preferred Language')
            ->assertJsonPath('review.events.0.event_type', 'application_submitted')
            ->assertJsonPath('review.events.0.metadata.declaration_accepted', true)
            ->assertJsonPath('review.can_view_documents', true);
    }

    public function test_application_document_download_is_private_and_institution_scoped(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $adminA = $this->makeInstitutionUser('documents.a@test', $this->institutionA);
        $adminB = $this->makeInstitutionUser('documents.b@test', $this->institutionB);
        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);
        $number = $this->createTestApplication($intake, $this->applicationPayload($intake->id))->application_number;
        $application = Application::where('application_number', $number)->firstOrFail();
        $storageKey = "admissions/{$this->institutionA->id}/applications/{$application->id}/proof.pdf";
        \Illuminate\Support\Facades\Storage::disk('local')->put($storageKey, '%PDF-1.4 private test file');
        $documentId = DB::table('admission_application_documents')->insertGetId([
            'institution_id' => $this->institutionA->id,
            'application_id' => $application->id,
            'document_type' => 'academic_certificate',
            'original_filename' => 'certificate.pdf',
            'storage_disk' => 'local',
            'storage_key' => $storageKey,
            'mime_type' => 'application/pdf',
            'file_size' => 25,
            'verification_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($adminA);
        $this->getJson("/api/admissions/applications/{$application->id}")
            ->assertOk()
            ->assertJsonPath('review.documents.0.original_filename', 'certificate.pdf')
            ->assertJsonPath('review.documents.0.download_url', "/api/admissions/applications/{$application->id}/documents/{$documentId}");
        $this->get("/api/admissions/applications/{$application->id}/documents/{$documentId}")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        Sanctum::actingAs($adminB);
        $this->getJson("/api/admissions/applications/{$application->id}")->assertNotFound();
        $this->get("/api/admissions/applications/{$application->id}/documents/{$documentId}")->assertNotFound();
    }

    public function test_admissions_officer_with_document_permission_can_download_documents(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $officer = $this->makeInstitutionUser('admissions.officer@test', $this->institutionA, isAdmin: false);
        $officerRole = Role::create(['code' => 'admissions_officer', 'name' => 'Admissions Officer', 'status' => 'active']);
        $permissionId = DB::table('permissions')->insertGetId([
            'code' => 'admissions.documents.view',
            'name' => 'View application documents',
            'module' => 'admissions',
            'description' => 'View applicant documents.',
            'status' => 'active',
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('role_permission')->insert([
            'role_id' => $officerRole->id,
            'permission_id' => $permissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_role_assignments')->insert([
            'institution_id' => $this->institutionA->id,
            'user_id' => $officer->id,
            'role_id' => $officerRole->id,
            'academic_unit_id' => null,
            'is_primary' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);
        $number = $this->createTestApplication($intake, $this->applicationPayload($intake->id))->application_number;
        $application = Application::where('application_number', $number)->firstOrFail();
        $storageKey = "admissions/{$this->institutionA->id}/applications/{$application->id}/proof.pdf";
        \Illuminate\Support\Facades\Storage::disk('local')->put($storageKey, 'private');
        $documentId = DB::table('admission_application_documents')->insertGetId([
            'institution_id' => $this->institutionA->id,
            'application_id' => $application->id,
            'document_type' => 'academic_certificate',
            'original_filename' => 'certificate.pdf',
            'storage_disk' => 'local',
            'storage_key' => $storageKey,
            'mime_type' => 'application/pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($officer);
        $this->getJson("/api/admissions/applications/{$application->id}")
            ->assertOk()
            ->assertJsonPath('review.can_view_documents', true);
        $this->get("/api/admissions/applications/{$application->id}/documents/{$documentId}")
            ->assertOk();
    }

    public function test_user_without_document_permission_cannot_download_documents(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $faculty = $this->makeInstitutionUser('documents.denied@test', $this->institutionA, isAdmin: false);
        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);
        $number = $this->createTestApplication($intake, $this->applicationPayload($intake->id))->application_number;
        $application = Application::where('application_number', $number)->firstOrFail();
        $storageKey = "admissions/{$this->institutionA->id}/applications/{$application->id}/proof.pdf";
        \Illuminate\Support\Facades\Storage::disk('local')->put($storageKey, 'private');
        $documentId = DB::table('admission_application_documents')->insertGetId([
            'institution_id' => $this->institutionA->id,
            'application_id' => $application->id,
            'document_type' => 'academic_certificate',
            'original_filename' => 'certificate.pdf',
            'storage_disk' => 'local',
            'storage_key' => $storageKey,
            'mime_type' => 'application/pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($faculty);
        $this->getJson("/api/admissions/applications/{$application->id}")
            ->assertOk()
            ->assertJsonPath('review.can_view_documents', false)
            ->assertJsonCount(0, 'review.documents');
        $this->get("/api/admissions/applications/{$application->id}/documents/{$documentId}")
            ->assertForbidden();
    }

    public function test_applications_are_isolated_per_institution(): void
    {
        $adminA = $this->makeInstitutionUser('admin.a@test', $this->institutionA);
        $adminB = $this->makeInstitutionUser('admin.b@test', $this->institutionB);

        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);
        $number = $this->createTestApplication($intake, $this->applicationPayload($intake->id))->application_number;

        Sanctum::actingAs($adminB);

        $applicationId = Application::where('application_number', $number)->value('id');
        $this->getJson("/api/admissions/applications/{$applicationId}")->assertNotFound();
        $this->patchJson("/api/admissions/applications/{$applicationId}/decision", ['status' => 'shortlisted'])
            ->assertNotFound();
    }

    private function makeSubmittedApplicantApplication(): array
    {
        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);
        $applicant = AdmissionApplicant::create([
            'institution_id' => $this->institutionA->id,
            'full_name' => 'Riya Sharma',
            'email' => 'riya.applicant@example.test',
            'normalized_email' => 'riya.applicant@example.test',
            'mobile' => '9876543210',
            'password' => 'ApplicantStrongPass123',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        $application = Application::create([
            'institution_id' => $this->institutionA->id,
            'applicant_id' => $applicant->id,
            'intake_id' => $intake->id,
            'first_name' => 'Riya',
            'last_name' => 'Sharma',
            'email' => $applicant->email,
            'phone' => '9876543210',
            'application_number' => 'APP-' . strtoupper(uniqid()),
            'status' => 'under_review',
        ]);

        return [$applicant, $application];
    }

    public function test_institution_can_request_clarification_applicant_can_respond_and_institution_can_resolve(): void
    {
        [$applicant, $application] = $this->makeSubmittedApplicantApplication();
        $admin = $this->makeInstitutionUser('clarification.admin@test', $this->institutionA);

        Sanctum::actingAs($admin);
        $created = $this->postJson("/api/admissions/applications/{$application->id}/clarifications", [
            'request_text' => 'Please upload a clearer copy of your marksheet.',
            'requested_fields' => ['marksheet', 'passing_year'],
            'due_at' => now()->addDays(5)->toISOString(),
        ]);
        $created->assertCreated()->assertJsonPath('clarification.status', 'open');
        $clarificationId = $created->json('clarification.id');

        Sanctum::actingAs($applicant, ['applicant']);
        $this->getJson("/api/applicant/applications/{$application->id}/clarifications")
            ->assertOk()->assertJsonPath('data.0.id', $clarificationId);
        $this->postJson("/api/applicant/applications/{$application->id}/clarifications/{$clarificationId}/respond", [
            'applicant_response' => 'I have uploaded a clearer marksheet for your review.',
        ])->assertOk()->assertJsonPath('clarification.status', 'responded');

        Sanctum::actingAs($admin);
        $this->patchJson("/api/admissions/applications/{$application->id}/clarifications/{$clarificationId}/resolve", [
            'resolution_notes' => 'The updated document is satisfactory.',
        ])->assertOk()->assertJsonPath('clarification.status', 'resolved');

        $this->assertDatabaseHas('admission_application_events', [
            'application_id' => $application->id,
            'event_type' => 'clarification_responded',
            'actor_type' => 'applicant',
        ]);
        $this->assertDatabaseHas('admission_application_events', [
            'application_id' => $application->id,
            'event_type' => 'clarification_resolved',
        ]);
    }

    public function test_applicant_cannot_respond_to_another_applicants_clarification_or_twice(): void
    {
        [$applicant, $application] = $this->makeSubmittedApplicantApplication();
        $admin = $this->makeInstitutionUser('clarification.owner@test', $this->institutionA);
        Sanctum::actingAs($admin);
        $clarificationId = $this->postJson("/api/admissions/applications/{$application->id}/clarifications", [
            'request_text' => 'Please confirm your date of birth.',
        ])->assertCreated()->json('clarification.id');

        $otherApplicant = AdmissionApplicant::create([
            'institution_id' => $this->institutionA->id,
            'full_name' => 'Other Applicant',
            'email' => 'other.applicant@example.test',
            'normalized_email' => 'other.applicant@example.test',
            'mobile' => '9876543211',
            'password' => 'ApplicantStrongPass123',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        Sanctum::actingAs($otherApplicant, ['applicant']);

        $this->postJson("/api/applicant/applications/{$application->id}/clarifications/{$clarificationId}/respond", [
            'applicant_response' => 'This is not my application.',
        ])->assertNotFound();
        
        Sanctum::actingAs($otherApplicant, ['applicant']);
        $this->postJson("/api/applicant/applications/{$application->id}/clarifications/{$clarificationId}/respond", [
            'applicant_response' => 'This is not my application.',
        ])->assertNotFound();

        Sanctum::actingAs($applicant, ['applicant']);
        $url = "/api/applicant/applications/{$application->id}/clarifications/{$clarificationId}/respond";
        $this->postJson($url, ['applicant_response' => 'My date of birth is correct.'])->assertOk();
        $this->postJson($url, ['applicant_response' => 'Trying to overwrite my response.'])->assertStatus(409);
    }

    public function test_institution_can_verify_or_return_documents_with_audit_events(): void
    {
        [, $application] = $this->makeSubmittedApplicantApplication();
        $admin = $this->makeInstitutionUser('document.review@test', $this->institutionA);
        $documentId = DB::table('admission_application_documents')->insertGetId([
            'institution_id' => $this->institutionA->id,
            'application_id' => $application->id,
            'document_type' => 'marksheet',
            'original_filename' => 'marksheet.pdf',
            'storage_disk' => 'local',
            'storage_key' => 'admissions/test/marksheet.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1200,
            'verification_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/admissions/applications/{$application->id}/documents/{$documentId}/verification", [
            'verification_status' => 'returned',
        ])->assertUnprocessable();
        $this->patchJson("/api/admissions/applications/{$application->id}/documents/{$documentId}/verification", [
            'verification_status' => 'returned',
            'review_notes' => 'The scan is not legible. Please upload a clearer copy.',
        ])->assertOk()->assertJsonPath('document.verification_status', 'returned');

        $this->patchJson("/api/admissions/applications/{$application->id}/documents/{$documentId}/verification", [
            'verification_status' => 'verified',
        ])->assertOk()->assertJsonPath('document.verification_status', 'verified');

        $this->assertDatabaseHas('admission_application_events', [
            'application_id' => $application->id,
            'event_type' => 'document_returned',
        ]);
        $this->assertDatabaseHas('admission_application_events', [
            'application_id' => $application->id,
            'event_type' => 'document_verified',
        ]);
    }

    public function test_non_privileged_institution_user_cannot_create_clarification_or_verify_document(): void
    {
        [, $application] = $this->makeSubmittedApplicantApplication();
        $user = $this->makeInstitutionUser('no.admission.permission@test', $this->institutionA, isAdmin: false);
        Sanctum::actingAs($user);

        $this->postJson("/api/admissions/applications/{$application->id}/clarifications", [
            'request_text' => 'Please provide additional details.',
        ])->assertForbidden();
        $this->patchJson("/api/admissions/applications/{$application->id}/documents/1/verification", [
            'verification_status' => 'verified',
        ])->assertForbidden();
    }

    public function test_institution_cannot_access_another_institutions_application_clarification_or_documents(): void
    {
        [, $application] = $this->makeSubmittedApplicantApplication();
        $otherAdmin = $this->makeInstitutionUser('other.institution.admin@test', $this->institutionB);
        $documentId = DB::table('admission_application_documents')->insertGetId([
            'institution_id' => $this->institutionA->id,
            'application_id' => $application->id,
            'document_type' => 'marksheet',
            'original_filename' => 'marksheet.pdf',
            'storage_disk' => 'local',
            'storage_key' => 'admissions/test/marksheet.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1200,
            'verification_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($otherAdmin);

        $this->postJson("/api/admissions/applications/{$application->id}/clarifications", [
            'request_text' => 'Please provide additional details.',
        ])->assertNotFound();
        $this->getJson("/api/admissions/applications/{$application->id}")->assertNotFound();
        $this->patchJson("/api/admissions/applications/{$application->id}/documents/{$documentId}/verification", [
            'verification_status' => 'verified',
        ])->assertNotFound();
    }

    public function test_clarification_validation_and_invalid_state_transitions_are_rejected(): void
    {
        [$applicant, $application] = $this->makeSubmittedApplicantApplication();
        $admin = $this->makeInstitutionUser('clarification.validation@test', $this->institutionA);
        Sanctum::actingAs($admin);

        $this->postJson("/api/admissions/applications/{$application->id}/clarifications", [
            'request_text' => 'no',
            'due_at' => now()->subDay()->toISOString(),
        ])->assertUnprocessable();

        $clarificationId = $this->postJson("/api/admissions/applications/{$application->id}/clarifications", [
            'request_text' => 'Please confirm the name on your certificate.',
        ])->assertCreated()->json('clarification.id');

        $this->patchJson("/api/admissions/applications/{$application->id}/clarifications/{$clarificationId}/resolve", [])
            ->assertStatus(422);

        Sanctum::actingAs($applicant, ['applicant']);
        $this->postJson("/api/applicant/applications/{$application->id}/clarifications/{$clarificationId}/respond", [
            'applicant_response' => 'The name is correct as shown on the certificate.',
        ])->assertOk();

        Sanctum::actingAs($admin);
        $this->patchJson("/api/admissions/applications/{$application->id}/clarifications/{$clarificationId}/resolve", [])->assertOk();
        $this->patchJson("/api/admissions/applications/{$application->id}/clarifications/{$clarificationId}/resolve", [])->assertStatus(409);
    }


    public function test_admissions_officer_with_scrutiny_and_document_permissions_can_perform_workflow_actions(): void
    {
        [, $application] = $this->makeSubmittedApplicantApplication();
        $role = Role::create(['code' => 'admissions_officer', 'name' => 'Admissions Officer', 'status' => 'active']);
        $user = User::create([
            'account_type' => 'institution',
            'institution_id' => $this->institutionA->id,
            'name' => 'Admissions Officer',
            'email' => 'admissions.officer@test',
            'password' => Hash::make(self::PASSWORD),
            'status' => 'active',
        ]);
        DB::table('user_role_assignments')->insert([
            'institution_id' => $this->institutionA->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'academic_unit_id' => null,
            'is_primary' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach (['admissions.applications.scrutinize', 'admissions.documents.verify'] as $code) {
            $permissionId = DB::table('permissions')->insertGetId([
                'code' => $code,
                'name' => $code,
                'module' => 'admissions',
                'status' => 'active',
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('role_permission')->insert([
                'role_id' => $role->id,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $documentId = DB::table('admission_application_documents')->insertGetId([
            'institution_id' => $this->institutionA->id,
            'application_id' => $application->id,
            'document_type' => 'marksheet',
            'original_filename' => 'marksheet.pdf',
            'storage_disk' => 'local',
            'storage_key' => 'admissions/test/marksheet.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1200,
            'verification_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $this->postJson("/api/admissions/applications/{$application->id}/clarifications", [
            'request_text' => 'Please confirm your previous institution name.',
        ])->assertCreated();
        $this->patchJson("/api/admissions/applications/{$application->id}/documents/{$documentId}/verification", [
            'verification_status' => 'verified',
        ])->assertOk()->assertJsonPath('document.verification_status', 'verified');
    }
    public function test_institution_admin_can_manage_intakes_with_date_validation_and_tenant_isolation(): void
    {
        $admin = $this->makeInstitutionUser('intake.admin@test', $this->institutionA);
        $foreignAdmin = $this->makeInstitutionUser('intake.foreign@test', $this->institutionB);
        Sanctum::actingAs($admin);

        $payload = [
            'name' => '2027 BTech CSE Intake',
            'programme_id' => $this->programme->id,
            'academic_session_id' => $this->session->id,
            'open_date' => '2027-01-01',
            'close_date' => '2027-06-30',
            'seat_capacity' => 80,
            'status' => 'closed',
            'application_fee' => 1200,
        ];

        $created = $this->postJson('/api/admissions/intakes', $payload)
            ->assertCreated()
            ->assertJsonPath('intake.name', '2027 BTech CSE Intake');
        $intakeId = $created->json('intake.id');

        // A partial close-date update must compare against the stored opening date.
        $this->patchJson("/api/admissions/intakes/{$intakeId}", ['close_date' => '2027-07-15'])
            ->assertOk();

        $this->patchJson("/api/admissions/intakes/{$intakeId}", ['close_date' => '2026-12-31'])
            ->assertUnprocessable();

        Sanctum::actingAs($foreignAdmin);
        $this->patchJson("/api/admissions/intakes/{$intakeId}", ['status' => 'open'])
            ->assertNotFound();
        $this->deleteJson("/api/admissions/intakes/{$intakeId}")
            ->assertNotFound();
    }

    public function test_application_list_rejects_invalid_filters_and_caps_page_size(): void
    {
        $admin = $this->makeInstitutionUser('intake.list.admin@test', $this->institutionA);
        $intake = $this->openIntake($this->institutionA, $this->programme, $this->session);
        $this->createTestApplication($intake, $this->applicationPayload($intake->id));
        Sanctum::actingAs($admin);

        $this->getJson('/api/admissions/applications?status=unknown')
            ->assertUnprocessable();
        $this->getJson('/api/admissions/applications?per_page=500')
            ->assertUnprocessable();
        $this->getJson('/api/admissions/applications?per_page=100')
            ->assertOk()
            ->assertJsonPath('per_page', 100);
    }

}
