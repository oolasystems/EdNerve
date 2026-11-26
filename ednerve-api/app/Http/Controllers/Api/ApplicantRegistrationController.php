<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdmissionApplicant;
use App\Models\AdmissionApplicantVerificationToken;
use App\Models\Institution;
use App\Notifications\AdmissionApplicantEmailVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApplicantRegistrationController extends Controller
{
    private const TOKEN_PURPOSE = 'email_verification';
    private const TOKEN_TTL_HOURS = 24;
    private const MAX_TOKEN_ATTEMPTS = 5;

    public function register(Request $request): JsonResponse
    {
        $request->merge(['email' => trim((string) $request->input('email'))]);
        $validated = $request->validate([
            'institution_code' => ['required', 'string', 'max:80'],
            'full_name' => ['required', 'string', 'min:2', 'max:180'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'mobile_country_code' => ['nullable', 'string', 'max:8'],
            'mobile' => ['required', 'string', 'max:30'],
        ]);

        $institution = $this->findActiveInstitution($validated['institution_code']);
        $email = trim($validated['email']);
        $normalizedEmail = mb_strtolower($email);

        $applicant = DB::transaction(function () use ($institution, $validated, $email, $normalizedEmail) {
            $existing = AdmissionApplicant::query()
                ->where('institution_id', $institution->id)
                ->where('normalized_email', $normalizedEmail)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                // Do not reveal whether the address is already registered.
                return $existing->email_verified_at ? null : $existing;
            }

            return AdmissionApplicant::create([
                'institution_id' => $institution->id,
                'full_name' => trim($validated['full_name']),
                'email' => $email,
                'normalized_email' => $normalizedEmail,
                'mobile_country_code' => $validated['mobile_country_code'] ?? null,
                'mobile' => trim($validated['mobile']),
                'status' => 'pending_verification',
            ]);
        });

        if ($applicant) {
            $this->issueVerificationToken($applicant, $institution);
        }

        return response()->json([
            'message' => 'If the registration can be processed, a verification email will be sent to the supplied address.',
        ], 202);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $request->merge(['email' => trim((string) $request->input('email'))]);
        $validated = $request->validate([
            'institution_code' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:190'],
        ]);

        $institution = $this->findActiveInstitution($validated['institution_code']);
        $normalizedEmail = mb_strtolower(trim($validated['email']));

        $applicant = AdmissionApplicant::query()
            ->where('institution_id', $institution->id)
            ->where('normalized_email', $normalizedEmail)
            ->whereNull('email_verified_at')
            ->where('status', 'pending_verification')
            ->first();

        if ($applicant) {
            $this->issueVerificationToken($applicant, $institution);
        }

        return response()->json([
            'message' => 'If the registration can be processed, a verification email will be sent to the supplied address.',
        ], 202);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $request->merge(['email' => trim((string) $request->input('email'))]);
        $validated = $request->validate([
            'institution_code' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'token' => ['required', 'string', 'size:64'],
        ]);

        $institution = $this->findActiveInstitution($validated['institution_code']);
        $normalizedEmail = mb_strtolower(trim($validated['email']));

        $applicant = AdmissionApplicant::query()
            ->where('institution_id', $institution->id)
            ->where('normalized_email', $normalizedEmail)
            ->first();

        if (! $applicant || $applicant->email_verified_at) {
            throw ValidationException::withMessages(['token' => 'The verification link is invalid or has expired.']);
        }

        $setupToken = Str::random(64);

        $result = DB::transaction(function () use ($applicant, $validated, $setupToken) {
            $verification = AdmissionApplicantVerificationToken::query()
                ->where('institution_id', $applicant->institution_id)
                ->where('applicant_id', $applicant->id)
                ->where('purpose', self::TOKEN_PURPOSE)
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $verification || $verification->expires_at->isPast()
                || $verification->attempt_count >= self::MAX_TOKEN_ATTEMPTS) {
                return false;
            }

            if (! hash_equals($verification->token_hash, hash('sha256', $validated['token']))) {
                $verification->increment('attempt_count');
                return false;
            }

            $verification->forceFill(['consumed_at' => now()])->save();
            $applicant->forceFill([
                'email_verified_at' => now(),
                'status' => 'pending_password_setup',
            ])->save();

            AdmissionApplicantVerificationToken::create([
                'institution_id' => $applicant->institution_id,
                'applicant_id' => $applicant->id,
                'purpose' => 'password_setup',
                'token_hash' => hash('sha256', $setupToken),
                'expires_at' => now()->addMinutes(30),
            ]);

            return true;
        });

        if (! $result) {
            throw ValidationException::withMessages(['token' => 'The verification link is invalid or has expired.']);
        }

        return response()->json([
            'message' => 'Email verified successfully. You can now set your password.',
            'next_step' => 'set_password',
            'setup_token' => $setupToken,
        ]);
    }

    public function setPassword(Request $request): JsonResponse
    {
        $request->merge(['email' => trim((string) $request->input('email'))]);
        $validated = $request->validate([
            'institution_code' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'setup_token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', 'min:12', 'max:128', 'confirmed'],
        ]);

        $institution = $this->findActiveInstitution($validated['institution_code']);
        $applicant = AdmissionApplicant::query()
            ->where('institution_id', $institution->id)
            ->where('normalized_email', mb_strtolower(trim($validated['email'])))
            ->first();

        if (! $applicant || ! $applicant->email_verified_at || $applicant->status !== 'pending_password_setup') {
            throw ValidationException::withMessages([
                'email' => 'Email verification must be completed before setting a password.',
            ]);
        }

        $passwordSet = DB::transaction(function () use ($applicant, $validated) {
            $setup = AdmissionApplicantVerificationToken::query()
                ->where('institution_id', $applicant->institution_id)
                ->where('applicant_id', $applicant->id)
                ->where('purpose', 'password_setup')
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $setup || $setup->expires_at->isPast()
                || ! hash_equals($setup->token_hash, hash('sha256', $validated['setup_token']))) {
                return false;
            }

            $setup->forceFill(['consumed_at' => now()])->save();
            $applicant->password = $validated['password'];
            $applicant->status = 'active';
            $applicant->save();

            return true;
        });

        if (! $passwordSet) {
            throw ValidationException::withMessages([
                'setup_token' => 'The password setup link is invalid or has expired.',
            ]);
        }

        return response()->json([
            'message' => 'Password set successfully. You can now sign in to the applicant portal.',
        ]);
    }

    private function findActiveInstitution(string $code): Institution
    {
        return Institution::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query
                ->where('slug', $code)
                ->orWhere('code', strtoupper($code)))
            ->firstOrFail();
    }

    private function issueVerificationToken(AdmissionApplicant $applicant, Institution $institution): void
    {
        $rawToken = Str::random(64);

        DB::transaction(function () use ($applicant, $rawToken) {
            AdmissionApplicantVerificationToken::query()
                ->where('institution_id', $applicant->institution_id)
                ->where('applicant_id', $applicant->id)
                ->where('purpose', self::TOKEN_PURPOSE)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now(), 'updated_at' => now()]);

            AdmissionApplicantVerificationToken::create([
                'institution_id' => $applicant->institution_id,
                'applicant_id' => $applicant->id,
                'purpose' => self::TOKEN_PURPOSE,
                'token_hash' => hash('sha256', $rawToken),
                'expires_at' => now()->addHours(self::TOKEN_TTL_HOURS),
            ]);
        });

        $verificationUrl = rtrim((string) config('app.frontend_url'), '/')
            . '/applicant/verify-email?'
            . http_build_query([
                'institution_code' => $institution->code,
                'email' => $applicant->email,
                'token' => $rawToken,
            ]);

        $applicant->notify(new AdmissionApplicantEmailVerification($verificationUrl, $institution->name));
    }
}
