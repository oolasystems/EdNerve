<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonPlanEntry extends Model
{
    protected $fillable = [
        'institution_id', 'subject_id', 'academic_session_id', 'syllabus_id', 'faculty_user_id',
        'programme_id', 'semester', 'section', 'unit_number', 'topic', 'planned_hours', 'taught_hours',
        'planned_date', 'taught_date', 'status', 'remarks', 'updated_by',
    ];

    protected $casts = [
        'semester' => 'integer',
        'planned_hours' => 'decimal:2',
        'taught_hours' => 'decimal:2',
        'planned_date' => 'date',
        'taught_date' => 'date',
    ];

    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function academicSession(): BelongsTo { return $this->belongsTo(AcademicSession::class, 'academic_session_id'); }
    public function syllabus(): BelongsTo { return $this->belongsTo(Syllabus::class); }
    public function faculty(): BelongsTo { return $this->belongsTo(User::class, 'faculty_user_id'); }
    public function programme(): BelongsTo { return $this->belongsTo(Programme::class); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
