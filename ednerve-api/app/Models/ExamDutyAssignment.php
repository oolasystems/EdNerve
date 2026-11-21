<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamDutyAssignment extends Model
{
    protected $fillable = [
        'institution_id',
        'exam_subject_id',
        'user_id',
        'duty_role_id',
        'status',
        'assigned_by',
        'assigned_at',
        'responded_at',
        'remarks',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function examSubject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dutyRole(): BelongsTo
    {
        return $this->belongsTo(ExamDutyRole::class, 'duty_role_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
