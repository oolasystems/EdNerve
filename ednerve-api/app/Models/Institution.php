<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    protected $fillable = [
        'code',
        'name',
        'slug',
        'institution_type',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'logo_path',
        'website',
        'applicant_portal_origin',
        'applicant_portal_entry_path',
        'status',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function academicUnits(): HasMany
    {
        return $this->hasMany(AcademicUnit::class);
    }

    public function userRoleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }
}
