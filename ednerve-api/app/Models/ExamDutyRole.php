<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamDutyRole extends Model
{
    protected $fillable = [
        'institution_id',
        'code',
        'name',
        'description',
        'min_qualification',
        'duty_weight',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'duty_weight' => 'integer',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(ExamDutyAssignment::class, 'duty_role_id');
    }
}
