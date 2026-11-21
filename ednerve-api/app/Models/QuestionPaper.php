<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionPaper extends Model
{
    protected $fillable = [
        'institution_id',
        'subject_id',
        'code',
        'instructions',
        'status',
        'source',
        'total_marks',
        'template_id',
        'numbering_scheme',
        'created_by',
        'prepared_by',
        'prepared_at',
        'checked_by',
        'checked_at',
        'approved_by',
        'approved_at',
        'ratified_by',
        'ratified_at',
    ];

    protected $casts = [
        'total_marks' => 'decimal:2',
        'approved_at' => 'datetime',
        'prepared_at' => 'datetime',
        'checked_at' => 'datetime',
        'ratified_at' => 'datetime',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PREPARED = 'prepared';
    public const STATUS_CHECKED = 'checked';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_RATIFIED = 'ratified';
    // legacy
    public const STATUS_FINAL = 'final';
    public const STATUS_RETIRED = 'retired';

    public const WORKFLOW_ORDER = [
        self::STATUS_DRAFT,
        self::STATUS_PREPARED,
        self::STATUS_CHECKED,
        self::STATUS_APPROVED,
        self::STATUS_RATIFIED,
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(QuestionPaperSection::class, 'paper_id')->orderBy('position');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuestionPaperItem::class, 'paper_id')->orderBy('position');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(QuestionPaperTemplate::class, 'template_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(QuestionPaperApproval::class, 'paper_id')->orderBy('id');
    }
}
