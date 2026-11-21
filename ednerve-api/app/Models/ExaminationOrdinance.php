<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaminationOrdinance extends Model
{
    protected $fillable = ['institution_id', 'code', 'name', 'description', 'grading_bands', 'pass_criteria', 'attendance_threshold', 'is_default', 'status'];
    protected $casts = ['grading_bands' => 'array', 'pass_criteria' => 'array', 'attendance_threshold' => 'decimal:2', 'is_default' => 'boolean'];
}
