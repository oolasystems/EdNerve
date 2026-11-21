<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyllabusUnit extends \Illuminate\Database\Eloquent\Model
{
    protected $fillable = [
        'institution_id',
        'syllabus_id',
        'position',
        'unit_number',
        'title',
        'topics',
        'learning_outcomes',
        'prescribed_hours',
    ];

    protected $casts = [
        'position' => 'integer',
        'prescribed_hours' => 'integer',
    ];

    public function syllabus(): BelongsTo
    {
        return $this->belongsTo(Syllabus::class);
    }
}
