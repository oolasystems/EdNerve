<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSession extends Model
{
    protected $fillable = ['institution_id', 'code', 'label', 'start_time', 'end_time', 'sort_order', 'active'];
    protected $casts = ['active' => 'boolean', 'sort_order' => 'integer'];
}
