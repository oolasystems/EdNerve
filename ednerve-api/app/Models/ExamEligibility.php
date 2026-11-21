<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamEligibility extends Model
{
    protected $fillable = ['institution_id', 'exam_subject_id', 'student_profile_id', 'status', 'attendance_pct', 'reason', 'decided_by', 'computed_at'];
    protected $casts = ['attendance_pct' => 'decimal:2', 'computed_at' => 'datetime'];
}
