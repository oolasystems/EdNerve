<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Shared account-type authorization for API controllers.
 *
 * Laravel-middleware (`platform` / `institution` aliases) gates the routes;
 * these helpers add the in-controller defense-in-depth check required by
 * the platform/institution permission model.
 */
trait RequiresAccountType
{
    protected function ensurePlatformAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->isPlatformAdmin(),
            403,
            'Platform Admin access required.'
        );
    }

    protected function ensureInstitutionAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->isInstitutionAdmin(),
            403,
            'Institution Administrator access required.'
        );
    }
}
