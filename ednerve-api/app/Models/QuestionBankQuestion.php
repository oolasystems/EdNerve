<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class QuestionBankQuestion extends Model
{
    protected $table = 'question_bank_questions';

    protected $fillable = [
        'institution_id',
        'subject_id',
        'unit',
        'difficulty',
        'type',
        'marks',
        'question_text',
        'model_answer',
        'tags',
        'status',
    ];

    protected $casts = [
        'marks' => 'decimal:2',
    ];

    /** Question text is encrypted at rest — decrypt on read, encrypt on write. */
    public function getQuestionTextAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            // Plaintext row (pre-encryption deploy or legacy) — return as-is.
            return $value;
        }
    }

    public function setQuestionTextAttribute($value): void
    {
        $this->attributes['question_text'] = $value ? Crypt::encryptString((string) $value) : null;
    }

    public function getModelAnswerAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    public function setModelAnswerAttribute($value): void
    {
        $this->attributes['model_answer'] = $value ? Crypt::encryptString((string) $value) : null;
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
