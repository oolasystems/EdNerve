<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $fillable = [
        'institution_id',
        'academic_unit_id',
        'code',
        'name',
        'credits',
        'subject_type',
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
        return $this->hasMany(ProgrammeSubject::class);
    }

    public function programmes()
    {
        return $this->belongsToMany(
            Programme::class,
            'programme_subjects',
            'subject_id',
            'programme_id'
        )->withPivot(['institution_id', 'semester', 'requirement_type', 'position', 'status', 'credit_override', 'teaching_hours']);
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
