<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Programme extends Model
{
    protected $fillable = [
        'institution_id',
        'academic_unit_id',
        'code',
        'name',
        'level',
        'duration_semesters',
        'status',
    ];

    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function programmeSubjects(): HasMany
    {
        return $this->hasMany(ProgrammeSubject::class)
            ->orderBy('semester')
            ->orderBy('position');
    }

    public function subjects()
    {
        return $this->belongsToMany(
            Subject::class,
            'programme_subjects',
            'programme_id',
            'subject_id'
        )->withPivot(['institution_id', 'semester', 'requirement_type', 'position', 'status'])
            ->orderBy('semester')
            ->orderBy('position');
    }
}
