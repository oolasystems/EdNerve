<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeStructure extends Model
{
    protected $fillable = [
        'institution_id',
        'programme_id',
        'academic_session_id',
        'fee_head_id',
        'amount',
        'frequency',
        'notes',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
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

    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class);
    }
}
