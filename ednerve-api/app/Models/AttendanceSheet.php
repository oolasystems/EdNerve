<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSheet extends Model
{
    protected $fillable = [
        'institution_id',
        'timetable_entry_id',
        'date',
        'marked_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(TimetableEntry::class, 'timetable_entry_id');
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'attendance_sheet_id');
    }
}
