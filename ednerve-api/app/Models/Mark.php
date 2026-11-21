<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mark extends Model
{
    protected $fillable = [
        'institution_id',
        'exam_component_id',
        'student_profile_id',
        'status',
        'marks_obtained',
        'remarks',
        'entered_by',
    ];

    protected $casts = [
        'marks_obtained' => 'decimal:2',
    ];

    public function component(): BelongsTo
    {
        return $this->belongsTo(ExamComponent::class, 'exam_component_id');
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }
}
