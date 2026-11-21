<?php

namespace App\Legacy;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RolePermissionResolver
{
    /** Return assignments effective for the requested scope. */
    public function effectiveAssignments(User $user, ?int $institutionId = null, ?int $organizationUnitId = null)
    {
        $now = now();

        return $user->roleAssignments()
            ->with(['role.permissions' => fn ($query) => $query->where('permissions.is_active', true)])
            ->where('user_role_assignments.is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->whereHas('role', function (Builder $q) use ($institutionId) {
                $q->where('roles.is_active', true)
                    ->where(function (Builder $scope) use ($institutionId) {
                        $scope->whereNull('roles.institution_id');
                        if ($institutionId !== null) {
                            $scope->orWhere('roles.institution_id', $institutionId);
                        }
                    });
            })
            ->where(function (Builder $scope) use ($institutionId) {
                $scope->whereNull('user_role_assignments.institution_id');
                if ($institutionId !== null) {
                    $scope->orWhere('user_role_assignments.institution_id', $institutionId);
                }
            })
            ->where(function (Builder $scope) use ($organizationUnitId) {
                if ($organizationUnitId === null) {
                    $scope->whereNull('user_role_assignments.academic_unit_id');
                } else {
                    $scope->whereNull('user_role_assignments.academic_unit_id')
                        ->orWhere('user_role_assignments.academic_unit_id', $organizationUnitId);
                }
            })
            ->get();
    }

    /**
     * Return every effective assignment belonging to the selected institution,
     * including assignments restricted to particular organization units.
     * Intended for context/bootstrap display, not authorization decisions.
     */
    public function effectiveAssignmentsForInstitution(User $user, int $institutionId): Collection
    {
        $now = now();

        return $user->roleAssignments()
            ->with([
                'role.permissions' => fn ($query) => $query->where('permissions.is_active', true),
                'organizationUnit',
            ])
            ->where('user_role_assignments.is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->whereHas('role', fn (Builder $q) => $q->where('roles.is_active', true)
                ->where(function (Builder $scope) use ($institutionId) {
                    $scope->whereNull('roles.institution_id')->orWhere('roles.institution_id', $institutionId);
                }))
            ->where(function (Builder $scope) use ($institutionId) {
                $scope->whereNull('user_role_assignments.institution_id')
                    ->orWhere('user_role_assignments.institution_id', $institutionId);
            })
            ->get();
    }

    public function permissionCodes(User $user, ?int $institutionId = null, ?int $organizationUnitId = null): Collection
    {
        return $this->effectiveAssignments($user, $institutionId, $organizationUnitId)
            ->flatMap(fn ($assignment) => $assignment->role?->permissions?->pluck('code') ?? collect())
            ->unique()
            ->values();
    }

    public function hasPermission(User $user, string $permissionCode, ?int $institutionId = null, ?int $organizationUnitId = null): bool
    {
        return $this->effectiveAssignments($user, $institutionId, $organizationUnitId)
            ->contains(fn ($assignment) => $assignment->role?->permissions?->contains('code', $permissionCode) ?? false);
    }

    public function roleCodes(User $user, ?int $institutionId = null, ?int $organizationUnitId = null): Collection
    {
        return $this->effectiveAssignments($user, $institutionId, $organizationUnitId)
            ->pluck('role.code')->filter()->unique()->values();
    }
}
