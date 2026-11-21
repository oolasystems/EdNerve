<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSubject extends Model
{
    protected $fillable = [
        'institution_id',
        'exam_id',
        'subject_id',
        'exam_date',
        'exam_session_id',
        'exam_room_id',
        'start_time',
        'end_time',
        'venue',
        'pass_percent',
        'notes',
        'instructions',
        'eligibility_threshold',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'pass_percent' => 'decimal:2',
        'start_time' => 'string',
        'end_time' => 'string',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(ExamComponent::class)->orderBy('position');
    }

    public function invigilations(): HasMany
    {
        return $this->hasMany(ExamInvigilation::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ExamRoom::class, 'exam_room_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }
}
