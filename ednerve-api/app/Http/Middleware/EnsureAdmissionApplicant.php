<?php

namespace App\Http\Middleware;

use App\Models\AdmissionApplicant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmissionApplicant
{
    public function handle(Request $request, Closure $next): Response
    {
        $applicant = $request->user();

        if (! ($applicant instanceof AdmissionApplicant)
            || $applicant->status !== 'active'
            || ! $applicant->email_verified_at
            || ! $applicant->institution()->where('status', 'active')->exists()) {
            return response()->json([
                'message' => 'An active, verified applicant account is required.',
            ], 403);
        }

        if (! $applicant->currentAccessToken()?->can('applicant')) {
            return response()->json([
                'message' => 'This token is not authorized for applicant access.',
            ], 403);
        }

        return $next($request);
    }
}
