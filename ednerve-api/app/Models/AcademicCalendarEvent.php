<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicCalendarEvent extends Model
{
    protected $fillable = [
        'institution_id',
        'academic_session_id',
        'title',
        'event_type',
        'starts_on',
        'ends_on',
        'is_all_day',
        'description',
        'status',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_all_day' => 'boolean',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }
}
