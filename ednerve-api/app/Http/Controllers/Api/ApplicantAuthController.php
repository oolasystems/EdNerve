<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdmissionApplicant;
use App\Models\Institution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ApplicantAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'institution_code' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'password' => ['required', 'string', 'max:128'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $institution = $this->findActiveInstitution($validated['institution_code']);
        $normalizedEmail = mb_strtolower(trim($validated['email']));

        $applicant = AdmissionApplicant::query()
            ->where('institution_id', $institution->id)
            ->where('normalized_email', $normalizedEmail)
            ->whereNotNull('email_verified_at')
            ->where('status', 'active')
            ->first();

        if (! $applicant || ! $applicant->password || ! Hash::check($validated['password'], $applicant->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid institution, email or password.'],
            ]);
        }

        // Applicant tokens are intentionally limited to applicant abilities.
        $token = $applicant->createToken(
            'applicant:'.($validated['device_name'] ?? 'web'),
            ['applicant'],
            now()->addDays(30)
        );

        $applicant->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'message' => 'Applicant signed in successfully.',
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toISOString(),
            'applicant' => $this->applicantPayload($applicant->fresh(['institution'])),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var AdmissionApplicant $applicant */
        $applicant = $request->user();

        return response()->json([
            'applicant' => $this->applicantPayload($applicant->loadMissing('institution')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Applicant signed out successfully.',
        ]);
    }

    private function findActiveInstitution(string $code): Institution
    {
        return Institution::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query
                ->where('slug', $code)
                ->orWhere('code', strtoupper($code)))
            ->first() ?? throw ValidationException::withMessages([
                'institution_code' => ['Invalid institution, email or password.'],
            ]);
    }

    private function applicantPayload(AdmissionApplicant $applicant): array
    {
        return [
            'id' => $applicant->id,
            'full_name' => $applicant->full_name,
            'email' => $applicant->email,
            'mobile_country_code' => $applicant->mobile_country_code,
            'mobile' => $applicant->mobile,
            'email_verified_at' => $applicant->email_verified_at?->toISOString(),
            'last_login_at' => $applicant->last_login_at?->toISOString(),
            'institution' => [
                'id' => $applicant->institution->id,
                'code' => $applicant->institution->code,
                'name' => $applicant->institution->name,
                'slug' => $applicant->institution->slug,
            ],
        ];
    }
}
