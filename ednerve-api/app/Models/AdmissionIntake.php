<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionIntake extends Model
{
    protected $fillable = [
        'institution_id',
        'programme_id',
        'academic_session_id',
        'name',
        'open_date',
        'close_date',
        'seat_capacity',
        'status',
        'application_fee',
    ];

    protected $casts = [
        'open_date' => 'date',
        'close_date' => 'date',
        'application_fee' => 'decimal:2',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'intake_id');
    }
}
