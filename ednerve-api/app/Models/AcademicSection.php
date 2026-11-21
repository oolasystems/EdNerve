<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicSection extends Model
{
    protected $fillable = [
        'institution_id',
        'academic_session_id',
        'programme_id',
        'semester',
        'code',
        'name',
        'capacity',
        'status',
    ];

    protected $casts = [
        'semester' => 'integer',
        'capacity' => 'integer',
    ];

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }
}
