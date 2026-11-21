<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionPaperSection extends Model
{
    protected $fillable = [
        'institution_id',
        'paper_id',
        'title',
        'instruction',
        'position',
    ];

    public function paper(): BelongsTo
    {
        return $this->belongsTo(QuestionPaper::class, 'paper_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuestionPaperItem::class, 'section_id')->orderBy('position');
    }
}
