<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMfaSecret extends Model
{
    protected $fillable = [
        'user_id',
        'secret',
        'confirmed_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
