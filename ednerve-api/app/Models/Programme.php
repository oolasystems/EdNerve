<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Programme extends Model
{
    protected $fillable = [
        'institution_id',
        'code',
        'name',
        'level',
        'duration_semesters',
        'status',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function programmeCourses(): HasMany
    {
        return $this->hasMany(ProgrammeCourse::class)
            ->orderBy('semester')
            ->orderBy('position');
    }

    public function subjects()
    {
        return $this->belongsToMany(
            Subject::class,
            'programme_courses',
            'programme_id',
            'subject_id'
        )->withPivot(['institution_id', 'semester', 'course_type', 'position', 'status'])
            ->orderBy('semester')
            ->orderBy('position');
    }
}
