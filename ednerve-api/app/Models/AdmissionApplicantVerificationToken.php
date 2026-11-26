<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionApplicantVerificationToken extends Model
{
    use HasFactory;

    protected $table = 'admission_applicant_verification_tokens';

    protected $fillable = [
        'institution_id',
        'applicant_id',
        'purpose',
        'token_hash',
        'expires_at',
        'consumed_at',
        'attempt_count',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempt_count' => 'integer',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(
            AdmissionApplicant::class,
            'applicant_id'
        );
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isUsable(): bool
    {
        return ! $this->isExpired() && ! $this->isConsumed();
    }
}
