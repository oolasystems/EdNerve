<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentSchemeComponent extends Model
{
    protected $fillable = ['institution_id', 'scheme_id', 'position', 'name', 'kind', 'max_marks', 'weightage', 'is_external', 'min_percent'];
    protected $casts = ['is_external' => 'boolean', 'max_marks' => 'decimal:2', 'weightage' => 'decimal:2', 'min_percent' => 'decimal:2', 'position' => 'integer'];
}
