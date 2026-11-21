<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevaluationRequest extends Model
{
    protected $fillable = [
        'institution_id',
        'exam_id',
        'student_profile_id',
        'subject_id',
        'reason',
        'fee_amount',
        'status',
        'requested_by',
        'handled_by',
    ];

    protected $casts = [
        'fee_amount' => 'decimal:2',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
