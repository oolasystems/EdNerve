<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamCentre extends Model
{
    protected $fillable = ['institution_id', 'code', 'name', 'address', 'capacity', 'active'];
    protected $casts = ['active' => 'boolean', 'capacity' => 'integer'];

    public function rooms(): HasMany
    {
        return $this->hasMany(ExamRoom::class, 'centre_id');
    }
}
