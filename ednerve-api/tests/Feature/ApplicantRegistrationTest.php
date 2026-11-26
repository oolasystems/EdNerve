<?php

namespace Tests\Feature;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionApplicantVerificationToken;
use App\Models\Institution;
use App\Notifications\AdmissionApplicantEmailVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApplicantRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Institution $institution;
    private Institution $otherInstitution;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institution = Institution::create([
            'code' => 'APPA',
            'name' => 'Applicant Test University',
            'slug' => 'appa',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        $this->otherInstitution = Institution::create([
            'code' => 'APPB',
            'name' => 'Other Test University',
            'slug' => 'appb',
            'institution_type' => 'university',
            'country' => 'India',
            'status' => 'active',
        ]);

        config(['app.frontend_url' => 'https://ednerve.example.test']);
    }

    public function test_applicant_can_register_and_only_a_token_hash_is_persisted(): void
    {
        Notification::fake();

        $this->postJson('/api/public/applicants/register', [
            'institution_code' => 'APPA',
            'full_name' => 'Aarav Sharma',
            'email' => 'Aarav@Example.Test',
            'mobile_country_code' => '+91',
            'mobile' => '9876543210',
        ])->assertAccepted();

        $applicant = AdmissionApplicant::firstOrFail();
        $this->assertSame('aarav@example.test', $applicant->normalized_email);
        $this->assertSame('pending_verification', $applicant->status);
        $this->assertNull($applicant->password);

        $token = AdmissionApplicantVerificationToken::firstOrFail();
        $this->assertSame($this->institution->id, $token->institution_id);
        $this->assertSame($applicant->id, $token->applicant_id);
        $this->assertSame(64, strlen($token->token_hash));
        $this->assertNull($token->consumed_at);

        Notification::assertSentTo($applicant, AdmissionApplicantEmailVerification::class);
    }

    public function test_duplicate_email_is_scoped_to_the_institution_and_does_not_create_another_applicant(): void
    {
        Notification::fake();

        $payload = [
            'institution_code' => 'APPA',
            'full_name' => 'Aarav Sharma',
            'email' => 'aarav@example.test',
            'mobile' => '9876543210',
        ];

        $this->postJson('/api/public/applicants/register', $payload)->assertAccepted();
        $this->postJson('/api/public/applicants/register', $payload)->assertAccepted();

        $this->assertDatabaseCount('admission_applicants', 1);

        $payload['institution_code'] = 'APPB';
        $this->postJson('/api/public/applicants/register', $payload)->assertAccepted();

        $this->assertDatabaseCount('admission_applicants', 2);
    }

    public function test_valid_email_verification_token_is_single_use_and_allows_password_setup(): void
    {
        $applicant = AdmissionApplicant::create([
            'institution_id' => $this->institution->id,
            'full_name' => 'Aarav Sharma',
            'email' => 'aarav@example.test',
            'normalized_email' => 'aarav@example.test',
            'mobile' => '9876543210',
            'status' => 'pending_verification',
        ]);

        $rawToken = str_repeat('a', 64);
        $verification = AdmissionApplicantVerificationToken::create([
            'institution_id' => $this->institution->id,
            'applicant_id' => $applicant->id,
            'purpose' => 'email_verification',
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addHours(24),
        ]);

        $payload = [
            'institution_code' => 'APPA',
            'email' => 'aarav@example.test',
            'token' => $rawToken,
        ];

        $verifyResponse = $this->postJson('/api/public/applicants/verify-email', $payload);
        $verifyResponse->assertOk()->assertJsonPath('next_step', 'set_password');
        $setupToken = $verifyResponse->json('setup_token');
        $this->assertIsString($setupToken);

        $this->assertNotNull($applicant->fresh()->email_verified_at);
        $this->assertSame('pending_password_setup', $applicant->fresh()->status);
        $this->assertNotNull($verification->fresh()->consumed_at);

        $this->postJson('/api/public/applicants/verify-email', $payload)->assertUnprocessable();

        $this->postJson('/api/public/applicants/set-password', [
            'institution_code' => 'APPA',
            'email' => 'aarav@example.test',
            'setup_token' => $setupToken,
            'password' => 'A_Strong_Password_123',
            'password_confirmation' => 'A_Strong_Password_123',
        ])->assertOk();

        $this->assertSame('active', $applicant->fresh()->status);
        $this->assertNotNull($applicant->fresh()->password);
    }

    public function test_verification_token_cannot_be_used_for_another_institution(): void
    {
        $applicant = AdmissionApplicant::create([
            'institution_id' => $this->institution->id,
            'full_name' => 'Aarav Sharma',
            'email' => 'aarav@example.test',
            'normalized_email' => 'aarav@example.test',
            'mobile' => '9876543210',
            'status' => 'pending_verification',
        ]);

        $rawToken = str_repeat('b', 64);
        AdmissionApplicantVerificationToken::create([
            'institution_id' => $this->institution->id,
            'applicant_id' => $applicant->id,
            'purpose' => 'email_verification',
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addHour(),
        ]);

        $this->postJson('/api/public/applicants/verify-email', [
            'institution_code' => 'APPB',
            'email' => 'aarav@example.test',
            'token' => $rawToken,
        ])->assertUnprocessable();

        $this->assertNull($applicant->fresh()->email_verified_at);
    }
}
