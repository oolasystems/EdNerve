<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentFeeDue extends Model
{
    protected $fillable = [
        'institution_id',
        'student_profile_id',
        'programme_id',
        'academic_session_id',
        'fee_head_id',
        'amount',
        'paid_amount',
        'due_date',
        'status',
        'waived_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function getBalanceAttribute(): float
    {
        return (float) $this->amount - (float) $this->paid_amount;
    }
}
