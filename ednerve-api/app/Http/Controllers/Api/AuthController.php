<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\AuthSessionService;
use App\Services\SecurityAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly AuthSessionService $sessions,
        private readonly SecurityAlertService $alerts
    ) {}

    /**
     * Authenticate using email and password.
     *
     * Platform users do not belong to a institution.
     * Institution users must belong to an active institution.
     */
    public function login(LoginRequest $request)
    {
        $validated = $request->validated();

        $email = strtolower(trim($validated['email']));

        // Email is globally unique across the users table.
        $user = User::where('email', $email)
            ->where('status', 'active')
            ->first();

        // Use the same error for unknown email and incorrect password.
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            $this->audit->record(
                $request,
                event: 'auth.login_failed',
                module: 'auth',
                newValues: ['email' => $email],
                description: 'Failed login attempt.',
            );

            // Anomaly detection: threshold on recent failures (per email and per IP).
            $threshold = (int) config('security.failed_login_threshold', 5);
            $window = (int) config('security.failed_login_window_minutes', 15);
            $since = now()->subMinutes($window);

            $failures = \App\Models\AuditLog::query()
                ->where('event', 'auth.login_failed')
                ->where('created_at', '>=', $since)
                ->where(function ($q) use ($email, $request) {
                    $q->where('new_values->email', $email)
                        ->orWhere('ip_address', $request->ip());
                })
                ->count();

            if ($failures >= $threshold) {
                $this->alerts->record(
                    kind: 'auth.repeated_failures',
                    severity: 'high',
                    message: "Repeated failed sign-in attempts for {$email} ({$failures} in {$window} minutes).",
                    institutionId: $user?->institution_id,
                    context: ['email' => $email, 'count' => $failures, 'ip' => $request->ip()],
                    ip: $request->ip(),
                );
            }

            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        /*
         * Validate the account type and institution association.
         */
        if ($user->account_type === 'platform') {

            // Platform accounts must not be associated with a institution.
            if ($user->institution_id !== null) {
                throw ValidationException::withMessages([
                    'email' => ['This account is not configured correctly.'],
                ]);
            }
        } elseif ($user->account_type === 'institution') {

            // Institution accounts must belong to an active institution.
            if (
                $user->institution_id === null ||
                !$user->institution()->where('status', 'active')->exists()
            ) {
                throw ValidationException::withMessages([
                    'email' => ['This institution account is unavailable.'],
                ]);
            }
        } else {
            // Reject unknown account types.
            throw ValidationException::withMessages([
                'email' => ['This account is not configured correctly.'],
            ]);
        }

        // MFA gate: verified users receive a scoped challenge token instead of a session token.
        if ($user->hasMfaEnabled()) {
            $payload = [
                'user_id' => $user->id,
                'exp' => now()->addMinutes(5)->timestamp,
            ];

            return response()->json([
                'mfa_required' => true,
                'mfa_token' => Crypt::encryptString(json_encode($payload)),
            ]);
        }

        return response()->json(
            $this->sessions->issue($user, $request, $validated['device_name'] ?? null)
        );
    }

    /**
     * Return authenticated user details.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if ($user->account_type === 'institution') {

            // Institution accounts must belong to an active institution.
            if (
                $user->institution_id === null ||
                !$user->institution()->where('status', 'active')->exists()
            ) {
                return response()->json([
                    'message' => 'Your institution account is unavailable because the institution is inactive.',
                ], 403);
            }

            $user->load([
                'institution',
                'activeRoleAssignments.role',
                'activeRoleAssignments.organizationUnit',
            ]);
        } elseif ($user->account_type === 'platform') {

            // Platform accounts have no institution or institution roles.
            if ($user->institution_id !== null) {
                return response()->json([
                    'message' => 'This platform account is not configured correctly.',
                ], 403);
            }

            $user->load('institution');
        } else {

            return response()->json([
                'message' => 'Invalid account type.',
            ], 403);
        }

        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
