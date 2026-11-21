<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredentialVerification extends Model
{
    protected $fillable = [
        'credential_id',
        'credential_number',
        'result',
        'visitor_ip',
        'user_agent',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }
}
