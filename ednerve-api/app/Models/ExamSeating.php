<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSeating extends Model
{
    protected $fillable = ['institution_id', 'exam_subject_id', 'room_id', 'student_profile_id', 'seat_number', 'roll_number'];
    protected $casts = ['seat_number' => 'integer'];
}
