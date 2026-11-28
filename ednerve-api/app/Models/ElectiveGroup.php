<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ElectiveGroup extends Model
{
    protected $fillable = [
        'institution_id',
        'programme_id',
        'semester',
        'code',
        'name',
        'required_choices',
        'status',
    ];

    protected $casts = [
        'semester' => 'integer',
        'required_choices' => 'integer',
    ];

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function programmeSubjects(): BelongsToMany
    {
        return $this->belongsToMany(
            ProgrammeSubject::class,
            'elective_group_subjects',
            'elective_group_id',
            'programme_subject_id'
        )->withPivot(['institution_id', 'programme_id'])->withTimestamps();
    }
}
