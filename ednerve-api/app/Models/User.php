<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'account_type',
        'institution_id',
        'name',
        'email',
        'phone',
        'password',
        'profile_photo',
        'status',
        'email_verified_at',
        'phone_verified_at',
        'last_login_at',
    ];

    /**
     * Attributes hidden from JSON responses.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Institution associated with this user.
     *
     * Returns null for Platform Admin accounts.
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * All role assignments associated with this user.
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }

    /**
     * Active role assignments within their validity period.
     */
    public function activeRoleAssignments(): HasMany
    {
        return $this->roleAssignments()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhereDate('starts_at', '<=', today());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', today());
            });
    }

    /**
     * The user's TOTP MFA secret, if one exists.
     */
    public function mfaSecret(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UserMfaSecret::class);
    }

    /**
     * Whether MFA is enrolled and confirmed for this user.
     */
    public function hasMfaEnabled(): bool
    {
        return $this->mfaSecret()
            ->whereNotNull('confirmed_at')
            ->exists();
    }

    /**
     * Whether the account type or role requires MFA to use privileged features.
     */
    public function requiresPrivilegedMfa(): bool
    {
        if ($this->isPlatformAdmin()) {
            return true;
        }

        return $this->hasRole('institution_admin')
            || $this->hasRole('examination_officer');
    }

    /*
    |--------------------------------------------------------------------------
    | Account Type Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the user is a Platform Admin.
     */
    public function isPlatformAdmin(): bool
    {
        return $this->account_type === 'platform'
            && $this->institution_id === null;
    }

    /**
     * Determine whether the user is an Institution User.
     */
    public function isInstitutionUser(): bool
    {
        return $this->account_type === 'institution'
            && $this->institution_id !== null;
    }

    /*
    |--------------------------------------------------------------------------
    | Role and Authorization Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Check whether the user has a specific active role.
     */
    public function hasRole(string $roleCode): bool
    {
        // Platform accounts do not use institution roles.
        if (!$this->isInstitutionUser()) {
            return false;
        }

        return $this->activeRoleAssignments()
            ->whereHas('role', function ($query) use ($roleCode) {
                $query->where('code', $roleCode)
                    ->where('status', 'active');
            })
            ->exists();
    }

    /**
     * Determine whether the user is an Institution Admin.
     */
    public function isInstitutionAdmin(): bool
    {
        return $this->hasRole('institution_admin');
    }

    /**
     * Determine whether the user is a Faculty member.
     */
    public function isFaculty(): bool
    {
        return $this->hasRole('faculty')
            || $this->hasRole('hod')
            || $this->hasRole('dean');
    }
}
