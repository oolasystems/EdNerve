<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    protected $fillable = [
        'institution_id',
        'faculty_id',
        'code',
        'name',
        'status',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(
            Faculty::class,
            'faculty_id'
        );
    }

    public function academicUnits(): HasMany
    {
        return $this->hasMany(AcademicUnit::class);
    }
}
