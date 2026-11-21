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
        'website',
        'address',
        'city',
        'state',
        'country',
        'logo_path',
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
