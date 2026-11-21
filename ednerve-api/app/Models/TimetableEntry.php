<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimetableEntry extends Model
{
    protected $fillable = [
        'institution_id',
        'programme_id',
        'semester',
        'section',
        'day_of_week',
        'period_no',
        'subject_id',
        'faculty_user_id',
        'room',
        'status',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'faculty_user_id');
    }

    public function sheets(): HasMany
    {
        return $this->hasMany(AttendanceSheet::class, 'timetable_entry_id');
    }
}
