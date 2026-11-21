<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $fillable = [
        'institution_id',
        'code',
        'name',
        'credits',
        'subject_type',
        'status',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function programmeCourses(): HasMany
    {
        return $this->hasMany(ProgrammeCourse::class);
    }

    public function programmes()
    {
        return $this->belongsToMany(
            Programme::class,
            'programme_courses',
            'subject_id',
            'programme_id'
        )->withPivot(['institution_id', 'semester', 'course_type', 'position', 'status', 'credit_override', 'teaching_hours']);
    }

    public function components(): HasMany
    {
        return $this->hasMany(SubjectComponent::class)->orderBy('position')->orderBy('id');
    }

    public function prerequisites(): HasMany
    {
        return $this->hasMany(SubjectPrerequisite::class);
    }

    public function syllabi(): HasMany
    {
        return $this->hasMany(Syllabus::class);
    }
}
