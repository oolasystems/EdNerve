<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamRoom extends Model
{
    protected $fillable = ['institution_id', 'centre_id', 'code', 'name', 'room_type', 'capacity', 'active'];
    protected $casts = ['active' => 'boolean', 'capacity' => 'integer'];

    public function centre(): BelongsTo
    {
        return $this->belongsTo(ExamCentre::class, 'centre_id');
    }
}
