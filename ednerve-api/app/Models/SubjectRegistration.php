<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectRegistration extends Model
{
    protected $fillable = [
        'institution_id',
        'student_profile_id',
        'academic_session_id',
        'programme_subject_id',
        'elective_group_id',
        'semester',
        'status',
        'registered_at',
        'registered_by',
    ];

    protected $casts = [
        'semester' => 'integer',
        'registered_at' => 'datetime',
    ];

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function programmeSubject(): BelongsTo
    {
        return $this->belongsTo(ProgrammeSubject::class, 'programme_subject_id');
    }

    public function electiveGroup(): BelongsTo
    {
        return $this->belongsTo(ElectiveGroup::class, 'elective_group_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
