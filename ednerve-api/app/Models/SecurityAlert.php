<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAlert extends Model
{
    protected $fillable = [
        'institution_id',
        'kind',
        'severity',
        'message',
        'context',
        'subject_user_id',
        'ip_address',
        'acknowledged_at',
        'acknowledged_by',
    ];

    protected $casts = [
        'context' => 'array',
        'acknowledged_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
