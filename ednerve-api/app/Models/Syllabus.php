<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Syllabus extends \Illuminate\Database\Eloquent\Model
{
    protected $fillable = [
        'institution_id',
        'subject_id',
        'academic_session_id',
        'version',
        'title',
        'status',
        'author_id',
        'approved_by',
        'approved_at',
        'published_by',
        'published_at',
        'superseded_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public const STATUSES = ['draft', 'in_review', 'approved', 'published', 'archived'];

    public const EDITABLE_STATUSES = ['draft'];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function units(): HasMany
    {
        return $this->hasMany(SyllabusUnit::class)->orderBy('position')->orderBy('id');
    }
}
