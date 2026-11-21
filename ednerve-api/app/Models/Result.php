<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    protected $fillable = [
        'institution_id',
        'exam_id',
        'student_profile_id',
        'total_obtained',
        'total_max',
        'percentage',
        'grade',
        'result_status',
        'revaluation_applied',
        'ordinance_id',
        'external_percent',
        'breakdown',
        'version',
        'computed_at',
    ];

    protected $casts = [
        'total_obtained' => 'decimal:2',
        'total_max' => 'decimal:2',
        'percentage' => 'decimal:2',
        'external_percent' => 'decimal:2',
        'breakdown' => 'array',
        'revaluation_applied' => 'boolean',
        'computed_at' => 'datetime',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }
}
