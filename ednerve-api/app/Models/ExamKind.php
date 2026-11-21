<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamKind extends Model
{
    protected $fillable = ['institution_id', 'code', 'label', 'max_attempts', 'active'];
    protected $casts = ['active' => 'boolean', 'max_attempts' => 'integer'];
}
