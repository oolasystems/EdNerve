<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamDutySwapRequest extends Model
{
    public const STATUS_PENDING_TARGET = 'pending_target';
    public const STATUS_PENDING_OFFICER = 'pending_officer';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';

    public const PENDING_STATUSES = [
        self::STATUS_PENDING_TARGET,
        self::STATUS_PENDING_OFFICER,
    ];

    protected $fillable = [
        'institution_id',
        'roster_id',
        'first_assignment_id',
        'second_assignment_id',
        'initiated_by',
        'target_user_id',
        'initiator_accepted_at',
        'target_accepted_at',
        'target_responded_at',
        'target_response_reason',
        'status',
        'decided_by',
        'decided_at',
        'decision_reason',
        'withdrawn_by',
        'withdrawn_at',
    ];

    protected $casts = [
        'initiator_accepted_at' => 'datetime',
        'target_accepted_at' => 'datetime',
        'target_responded_at' => 'datetime',
        'decided_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public function roster(): BelongsTo
    {
        return $this->belongsTo(ExamDutyRoster::class, 'roster_id');
    }

    public function firstAssignment(): BelongsTo
    {
        return $this->belongsTo(
            ExamDutyAssignment::class,
            'first_assignment_id'
        );
    }

    public function secondAssignment(): BelongsTo
    {
        return $this->belongsTo(
            ExamDutyAssignment::class,
            'second_assignment_id'
        );
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function withdrawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', self::PENDING_STATUSES);
    }

    public function scopeForInstitution(
        Builder $query,
        int $institutionId
    ): Builder {
        return $query->where('institution_id', $institutionId);
    }
}
