<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionPaperApproval extends Model
{
    public const STAGE_PREPARED = 'prepared';
    public const STAGE_CHECKED = 'checked';
    public const STAGE_APPROVED = 'approved';
    public const STAGE_RATIFIED = 'ratified';

    protected $fillable = [
        'institution_id',
        'paper_id',
        'stage',
        'decision',
        'actor_id',
        'remarks',
    ];

    public function paper(): BelongsTo
    {
        return $this->belongsTo(QuestionPaper::class, 'paper_id');
    }
}
