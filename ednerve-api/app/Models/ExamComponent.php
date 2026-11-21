<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamComponent extends Model
{
    protected $fillable = [
        'institution_id',
        'exam_subject_id',
        'name',
        'max_marks',
        'position',
    ];

    protected $casts = [
        'max_marks' => 'decimal:2',
    ];

    public function examSubject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class);
    }
}
