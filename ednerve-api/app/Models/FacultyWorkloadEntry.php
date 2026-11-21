<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacultyWorkloadEntry extends Model
{
    protected $fillable = [
        'institution_id', 'user_id', 'academic_session_id', 'subject_id', 'programme_id',
        'semester', 'section', 'periods_per_week', 'teaching_hours_per_week', 'role', 'notes', 'status',
    ];

    protected $casts = [
        'semester' => 'integer',
        'periods_per_week' => 'integer',
        'teaching_hours_per_week' => 'decimal:2',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function academicSession(): BelongsTo { return $this->belongsTo(AcademicSession::class, 'academic_session_id'); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function programme(): BelongsTo { return $this->belongsTo(Programme::class); }
}
