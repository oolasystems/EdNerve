<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicSession extends Model
{
    protected $fillable = [
        'institution_id',
        'code',
        'name',
        'start_date',
        'end_date',
        'is_active',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
