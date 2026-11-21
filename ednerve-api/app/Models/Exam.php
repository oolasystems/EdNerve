<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    protected $fillable = [
        'institution_id',
        'title',
        'kind',
        'academic_session_id',
        'programme_id',
        'semester',
        'section',
        'instructions',
        'status',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(ExamSubject::class);
    }
}
