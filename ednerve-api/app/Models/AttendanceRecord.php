<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'institution_id',
        'attendance_sheet_id',
        'student_profile_id',
        'status',
        'remarks',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(AttendanceSheet::class, 'attendance_sheet_id');
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }
}
