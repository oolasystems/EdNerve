<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Credential extends Model
{
    protected $fillable = [
        'institution_id',
        'credential_number',
        'student_profile_id',
        'type',
        'exam_id',
        'programme_id',
        'academic_session_id',
        'payload',
        'signature',
        'signature_algorithm',
        'public_key_id',
        'status',
        'issued_at',
        'expires_at',
        'revoked_at',
        'revoke_reason',
        'replaces_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
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
