<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectPrerequisite extends Model
{
    protected $fillable = [
        'institution_id',
        'subject_id',
        'prerequisite_subject_id',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function prerequisite(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'prerequisite_subject_id');
    }
}
