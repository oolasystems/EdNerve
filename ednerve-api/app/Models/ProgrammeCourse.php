<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgrammeCourse extends \Illuminate\Database\Eloquent\Model
{
    protected $fillable = [
        'institution_id',
        'programme_id',
        'subject_id',
        'semester',
        'course_type',
        'position',
        'credit_override',
        'teaching_hours',
        'status',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
