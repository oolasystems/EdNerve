<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamDutyAvailability extends Model
{
    protected $table = 'exam_duty_availability';

    protected $fillable = [
        'institution_id',
        'user_id',
        'exam_id',
        'exam_session_id',
        'duty_date',
        'available',
        'reason',
    ];

    protected $casts = [
        'available' => 'boolean',
        'duty_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }
}
