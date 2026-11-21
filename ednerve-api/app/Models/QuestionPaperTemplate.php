<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionPaperTemplate extends Model
{
    public const KIND_EXAM = 'exam';
    public const KIND_UNIT_TEST = 'unit_test';
    public const KIND_MID = 'mid';
    public const KIND_SESSIONAL = 'sessional';

    protected $fillable = [
        'institution_id',
        'name',
        'assessment_kind',
        'total_marks',
        'pattern',
        'instructions_md',
        'status',
    ];

    protected $casts = [
        'total_marks' => 'decimal:2',
        'pattern' => 'array',
    ];

    public function papers(): HasMany
    {
        return $this->hasMany(QuestionPaper::class, 'template_id');
    }
}
