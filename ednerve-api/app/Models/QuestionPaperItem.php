<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionPaperItem extends Model
{
    protected $fillable = [
        'institution_id',
        'paper_id',
        'section_id',
        'question_id',
        'position',
        'marks_override',
        'choice_group',
    ];

    protected $casts = [
        'marks_override' => 'decimal:2',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuestionBankQuestion::class, 'question_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(QuestionPaperSection::class, 'section_id');
    }
}
