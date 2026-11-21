<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExamDutyAssignment extends Model
{
    protected $fillable = [
        'institution_id',
        'roster_id',
        'exam_subject_id',
        'user_id',
        'duty_role_id',
        'replaced_by_assignment_id',
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

    public function roster(): BelongsTo
    {
        return $this->belongsTo(ExamDutyRoster::class, 'roster_id');
    }

    /**
     * The new assignment that replaced this assignment, if any.
     */
    public function replacementAssignment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_assignment_id');
    }

    /**
     * The original assignment that this assignment replaced, if any.
     */
    public function replaces(): HasOne
    {
        return $this->hasOne(self::class, 'replaced_by_assignment_id');
    }
}
