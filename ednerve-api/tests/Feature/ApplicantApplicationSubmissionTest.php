<?php

namespace Tests\Feature;

use App\Models\AdmissionApplicant;
use App\Models\AcademicSession;
use App\Models\AdmissionIntake;
use App\Models\Application;
use App\Models\Institution;
use App\Models\Programme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicantApplicationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private Institution $institution;
    private AdmissionApplicant $applicant;
    private Application $application;
    private int $versionId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->institution = Institution::create([
            'code' => 'SUBMIT',
            'name' => 'Submission Test University',
            'slug' => 'submission-test',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        $session = AcademicSession::create([
            'institution_id' => $this->institution->id,
            'code' => '2026-27',
            'name' => '2026-27 Session',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_active' => true,
            'status' => 'active',
        ]);

        $programme = Programme::create([
            'institution_id' => $this->institution->id,
            'code' => 'BSC',
            'name' => 'B.Sc.',
            'level' => 'UG',
            'duration_semesters' => 6,
            'status' => 'active',
        ]);

        $intake = AdmissionIntake::create([
            'institution_id' => $this->institution->id,
            'programme_id' => $programme->id,
            'academic_session_id' => $session->id,
            'name' => '2026 B.Sc. Intake',
            'open_date' => now()->subDay()->toDateString(),
            'close_date' => now()->addDays(20)->toDateString(),
            'seat_capacity' => 50,
            'status' => 'open',
            'application_fee' => 0,
        ]);

        $this->applicant = AdmissionApplicant::create([
            'institution_id' => $this->institution->id,
            'full_name' => 'Aarav Sharma',
            'email' => 'aarav@example.test',
            'normalized_email' => 'aarav@example.test',
            'mobile_country_code' => '+91',
            'mobile' => '9876543210',
            'email_verified_at' => now(),
            'status' => 'active',
            'password' => 'StrongPass@123',
        ]);

        $templateId = DB::table('admission_form_templates')->insertGetId([
            'institution_id' => $this->institution->id,
            'programme_id' => $programme->id,
            'intake_id' => $intake->id,
            'code' => 'SUBMISSION-TEST',
            'name' => 'Submission Test Form',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->versionId = DB::table('admission_form_versions')->insertGetId([
            'institution_id' => $this->institution->id,
            'template_id' => $templateId,
            'version_number' => 1,
            'status' => 'published',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $declarationSection = DB::table('admission_form_sections')->insertGetId([
            'institution_id' => $this->institution->id,
            'form_version_id' => $this->versionId,
            'section_key' => 'declarations',
            'title' => 'Declarations',
            'is_visible' => true,
            'is_required' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $documentSection = DB::table('admission_form_sections')->insertGetId([
            'institution_id' => $this->institution->id,
            'form_version_id' => $this->versionId,
            'section_key' => 'documents',
            'title' => 'Documents',
            'is_visible' => true,
            'is_required' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $academicSection = DB::table('admission_form_sections')->insertGetId([
            'institution_id' => $this->institution->id,
            'form_version_id' => $this->versionId,
            'section_key' => 'academic',
            'title' => 'Academic',
            'is_visible' => true,
            'is_required' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('admission_form_fields')->insert([
            [
                'institution_id' => $this->institution->id,
                'section_id' => $declarationSection,
                'field_key' => 'information_declaration',
                'label' => 'Declaration of correct information',
                'field_type' => 'boolean',
                'is_required' => true,
                'is_visible' => true,
                'is_applicant_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'institution_id' => $this->institution->id,
                'section_id' => $documentSection,
                'field_key' => 'photograph',
                'label' => 'Recent photograph',
                'field_type' => 'file',
                'is_required' => true,
                'is_visible' => true,
                'is_applicant_editable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->application = Application::create([
            'institution_id' => $this->institution->id,
            'applicant_id' => $this->applicant->id,
            'form_version_id' => $this->versionId,
            'intake_id' => $intake->id,
            'first_name' => 'Aarav',
            'last_name' => 'Sharma',
            'dob' => '2007-04-12',
            'email' => $this->applicant->email,
            'phone' => $this->applicant->mobile,
            'address_line' => '12 University Road',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'pin_code' => '411001',
            'application_number' => 'APP-TEST-0001',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($this->applicant, ['applicant']);
    }

    private function saveDeclaration(bool $accepted = true): void
    {
        DB::table('admission_application_answers')->insert([
            'institution_id' => $this->institution->id,
            'application_id' => $this->application->id,
            'form_version_id' => $this->versionId,
            'field_key' => 'information_declaration',
            'answer_value' => json_encode($accepted),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_submission_requires_explicit_declaration_acceptance(): void
    {
        $this->postJson("/api/applicant/applications/{$this->application->id}/submit", [
            'declaration_accepted' => false,
        ])->assertUnprocessable();

        $this->assertDatabaseHas('applications', [
            'id' => $this->application->id,
            'status' => 'draft',
        ]);
    }

    public function test_submission_requires_mandatory_documents_and_academic_qualification(): void
    {
        $this->saveDeclaration();

        $this->postJson("/api/applicant/applications/{$this->application->id}/submit", [
            'declaration_accepted' => true,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['documents.photograph', 'qualifications']);

        $this->assertDatabaseHas('applications', [
            'id' => $this->application->id,
            'status' => 'draft',
        ]);
    }

    public function test_applicant_can_upload_required_document_and_submit_atomically(): void
    {
        $this->saveDeclaration();

        DB::table('admission_application_qualifications')->insert([
            'institution_id' => $this->institution->id,
            'application_id' => $this->application->id,
            'qualification_level' => 'higher_secondary',
            'qualification_name' => 'Class XII',
            'board_or_university' => 'CBSE',
            'passing_year' => 2025,
            'result_status' => 'passed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post("/api/applicant/applications/{$this->application->id}/documents", [
            'document_type' => 'photograph',
            'file' => UploadedFile::fake()->create('applicant-photo.jpg', 100, 'image/jpeg'),
        ])->assertCreated()->assertJsonPath('document.document_type', 'photograph');

        $response = $this->postJson("/api/applicant/applications/{$this->application->id}/submit", [
            'declaration_accepted' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('application.status', 'under_review')
            ->assertJsonPath('application.application_number', 'APP-TEST-0001');

        $this->assertDatabaseHas('applications', [
            'id' => $this->application->id,
            'status' => 'under_review',
        ]);

        $this->assertDatabaseHas('admission_application_events', [
            'application_id' => $this->application->id,
            'event_type' => 'applicant_submitted',
            'from_status' => 'draft',
            'to_status' => 'under_review',
            'actor_type' => 'applicant',
            'actor_id' => $this->applicant->id,
        ]);

        $event = DB::table('admission_application_events')
            ->where('application_id', $this->application->id)
            ->where('event_type', 'applicant_submitted')
            ->first();

        $metadata = json_decode($event->metadata, true);
        $this->assertTrue($metadata['declaration_accepted']);
        $this->assertNotEmpty($metadata['declaration_accepted_at']);
    }

    public function test_duplicate_submission_is_rejected_and_only_one_event_is_recorded(): void
    {
        $this->saveDeclaration();

        DB::table('admission_application_qualifications')->insert([
            'institution_id' => $this->institution->id,
            'application_id' => $this->application->id,
            'qualification_level' => 'higher_secondary',
            'qualification_name' => 'Class XII',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post("/api/applicant/applications/{$this->application->id}/documents", [
            'document_type' => 'photograph',
            'file' => UploadedFile::fake()->create('applicant-photo.jpg', 100, 'image/jpeg'),
        ])->assertCreated();

        $payload = ['declaration_accepted' => true];
        $this->postJson("/api/applicant/applications/{$this->application->id}/submit", $payload)->assertOk();
        $this->postJson("/api/applicant/applications/{$this->application->id}/submit", $payload)->assertUnprocessable();

        $this->assertDatabaseCount('admission_application_events', 1);
        $this->assertDatabaseHas('applications', [
            'id' => $this->application->id,
            'status' => 'under_review',
        ]);
    }

    public function test_applicant_cannot_upload_documents_after_submission(): void
    {
        $this->application->update(['status' => 'under_review']);

        $this->post("/api/applicant/applications/{$this->application->id}/documents", [
            'document_type' => 'photograph',
            'file' => UploadedFile::fake()->create('applicant-photo.jpg', 100, 'image/jpeg'),
        ])->assertStatus(409);
    }

    public function test_applicant_can_replace_only_a_returned_document_after_submission(): void
    {
        $this->application->update(['status' => 'under_review']);
        $oldDocumentId = DB::table('admission_application_documents')->insertGetId([
            'institution_id' => $this->institution->id,
            'application_id' => $this->application->id,
            'document_type' => 'photograph',
            'original_filename' => 'old-photo.jpg',
            'storage_disk' => 'local',
            'storage_key' => 'admissions/old-photo.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 100,
            'verification_status' => 'returned',
            'review_notes' => 'Please upload a clearer image.',
            'created_at' => now()->subMinute(),
            'updated_at' => now(),
        ]);

        $response = $this->post("/api/applicant/applications/{$this->application->id}/documents", [
            'document_type' => 'photograph',
            'file' => UploadedFile::fake()->create('replacement-photo.jpg', 120, 'image/jpeg'),
        ]);

        $response->assertCreated()->assertJsonPath('document.verification_status', 'pending');
        $this->assertDatabaseHas('admission_application_documents', [
            'id' => $oldDocumentId,
            'verification_status' => 'superseded',
        ]);
        $this->assertDatabaseHas('admission_application_events', [
            'application_id' => $this->application->id,
            'event_type' => 'document_reuploaded',
            'actor_type' => 'applicant',
        ]);
        $this->assertSame('under_review', $this->application->fresh()->status);
    }

}
