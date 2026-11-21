<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicUnit extends Model
{
    protected $fillable = [
        'institution_id',
        'school_id',
        'faculty_id',
        'unit_type',
        'code',
        'name',
        'description',
        'status',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function userRoleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }
}
