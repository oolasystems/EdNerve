<?php

namespace App\Legacy;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class InstitutionContext
{
    public const SESSION_KEY = 'active_institution_id';

    public function accessibleInstitutions(User $user)
    {
        $memberships = $user->institutions()
            ->where('institutions.is_active', true)
            ->wherePivot('membership_status', 'active')
            ->orderBy('institutions.name')
            ->get([
                'institutions.id',
                'institutions.code',
                'institutions.name',
                'institutions.slug',
                'institutions.institution_type',
                'institutions.city',
                'institutions.state',
            ]);

        if ($this->isPlatformAdministrator($user)) {
            return Institution::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'id', 'code', 'name', 'slug',
                    'institution_type', 'city', 'state',
                ]);
        }

        return $memberships;
    }

    public function findAccessibleInstitution(User $user, int $institutionId): Institution
    {
        $institution = Institution::query()
            ->whereKey($institutionId)
            ->where('is_active', true)
            ->first();

        if (!$institution) {
            throw new AuthorizationException('The requested institution is unavailable.');
        }

        if (
            !$this->isPlatformAdministrator($user) &&
            !$user->institutions()
                ->where('institutions.id', $institutionId)
                ->where('institutions.is_active', true)
                ->wherePivot('membership_status', 'active')
                ->exists()
        ) {
            throw new AuthorizationException('You do not have an active membership in this institution.');
        }

        return $institution;
    }

    public function isPlatformAdministrator(User $user): bool
    {
        return $user->roleAssignments()
            ->whereNull('institution_id')
            ->whereNull('academic_unit_id')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->whereHas('role', fn ($query) => $query
                ->where('code', 'platform_admin')
                ->where('is_active', true))
            ->exists();
    }

    /**
     * Resolve context from a validated request header first, then SPA session.
     * A client-supplied ID is never trusted without membership verification.
     */
    public function resolveForRequest(Request $request): ?Institution
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        $requestedId = $request->header('X-Institution-ID');

        if ($requestedId !== null && $requestedId !== '') {
            if (!ctype_digit((string) $requestedId)) {
                abort(422, 'X-Institution-ID must be a valid numeric institution ID.');
            }

            $institution = $this->findAccessibleInstitution($user, (int) $requestedId);

            if ($request->hasSession()) {
                $request->session()->put(self::SESSION_KEY, $institution->id);
            }

            return $institution;
        }

        if ($request->hasSession()) {
            $sessionInstitutionId = $request->session()->get(self::SESSION_KEY);

            if ($sessionInstitutionId) {
                return $this->findAccessibleInstitution($user, (int) $sessionInstitutionId);
            }
        }

        return null;
    }
}
