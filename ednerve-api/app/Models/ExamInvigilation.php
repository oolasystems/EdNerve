<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamInvigilation extends Model
{
    protected $fillable = [
        'institution_id',
        'exam_subject_id',
        'user_id',
        'duty',
    ];

    public function examSubject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
