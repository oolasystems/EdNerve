<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce MFA enrolment for privileged users on sensitive write routes.
 *
 * Institution admins, examination officers and platform accounts must
 * have MFA confirmed before they can call the routes this guard protects.
 * Everyone else passes through.
 */
class EnsurePrivilegedMfaEnrolled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->requiresPrivilegedMfa() && !$user->hasMfaEnabled()) {
            return response()->json([
                'message' => 'MFA enrolment is required for this action. Set up MFA under Account Settings.',
                'code' => 'mfa_required',
            ], 403);
        }

        return $next($request);
    }
}
