<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class AdmissionApplicant extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'admission_applicants';

    protected $fillable = [
        'institution_id',
        'full_name',
        'email',
        'normalized_email',
        'email_verified_at',
        'mobile_country_code',
        'mobile',
        'mobile_verified_at',
        'password',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'applicant_id');
    }

    public function verificationTokens(): HasMany
    {
        return $this->hasMany(
            AdmissionApplicantVerificationToken::class,
            'applicant_id'
        );
    }
}
