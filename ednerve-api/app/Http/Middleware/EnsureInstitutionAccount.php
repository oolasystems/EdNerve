<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevent applicant Sanctum tokens from entering institutional API routes.
 * Platform and institution User accounts remain supported.
 */
class EnsureInstitutionAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! ($request->user() instanceof User)) {
            return response()->json([
                'message' => 'This endpoint requires an institutional or platform account.',
            ], 403);
        }

        return $next($request);
    }
}
