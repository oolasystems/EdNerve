<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Shared session issuance used by AuthController::login and the MFA challenge flow.
 * Keeps token creation, audit logging and eager loading in one place.
 */
class AuthSessionService
{
    public function __construct(
        private readonly AuditService $audit
    ) {}

    /**
     * Create the token + audit + user payload for a fully verified user.
     */
    public function issue(User $user, Request $request, ?string $deviceName = null): array
    {
        $user->last_login_at = now();
        $user->save();

        $token = $user->createToken($deviceName ?? 'ednerve-api')->plainTextToken;

        $this->audit->record(
            $request,
            event: 'auth.login_succeeded',
            module: 'auth',
            subject: $user,
            description: 'User authenticated.',
            institutionId: $user->institution_id,
            actorUserId: $user->id,
        );

        if ($user->account_type === 'institution') {
            $user->load([
                'institution',
                'activeRoleAssignments.role',
                'activeRoleAssignments.organizationUnit',
            ]);
        } elseif ($user->account_type === 'platform') {
            $user->load('institution');
        }

        return [
            'message' => 'Login successful.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ];
    }
}
