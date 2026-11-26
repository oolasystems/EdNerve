<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    protected $fillable = [
        'institution_id',
        'applicant_id',
        'form_version_id',
        'intake_id',
        'first_name',
        'middle_name',
        'last_name',
        'father_name',
        'mother_name',
        'dob',
        'gender',
        'category',
        'nationality',
        'email',
        'phone',
        'address_line',
        'city',
        'state',
        'pin_code',
        'last_exam_name',
        'last_exam_board_or_univ',
        'last_exam_year',
        'last_exam_percentage',
        'application_number',
        'status',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
        'enrolled_user_id',
    ];

    protected $casts = [
        'dob' => 'date',
        'last_exam_year' => 'integer',
        'last_exam_percentage' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplicant::class, 'applicant_id');
    }

    public function intake(): BelongsTo
    {
        return $this->belongsTo(AdmissionIntake::class, 'intake_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function enrolledUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_user_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])));
    }
}
