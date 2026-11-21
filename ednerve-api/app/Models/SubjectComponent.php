<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectComponent extends Model
{
    protected $fillable = [
        'institution_id',
        'subject_id',
        'kind',
        'credits',
        'hours_per_week',
        'max_marks',
        'position',
        'status',
    ];

    protected $casts = [
        'credits' => 'integer',
        'hours_per_week' => 'integer',
        'max_marks' => 'decimal:2',
        'position' => 'integer',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
