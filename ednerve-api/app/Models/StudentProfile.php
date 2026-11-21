<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProfile extends Model
{
    protected $fillable = [
        'institution_id',
        'user_id',
        'application_number',
        'programme_id',
        'academic_session_id',
        'enrollment_status',
        'admitted_on',
        'left_on',
        'father_name',
        'mother_name',
        'dob',
        'gender',
        'nationality',
        'category',
        'guardian_name',
        'guardian_relation',
        'guardian_phone',
        'address_line',
        'city',
        'state',
        'pin_code',
        'remarks',
    ];

    protected $casts = [
        'admitted_on' => 'date',
        'left_on' => 'date',
        'dob' => 'date',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }
}
