<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResultHistory extends Model
{
    protected $table = 'results_history';

    protected $fillable = [
        'institution_id',
        'exam_id',
        'student_profile_id',
        'total_obtained',
        'total_max',
        'percentage',
        'grade',
        'result_status',
        'revaluation_applied',
        'version',
        'superseded_by',
        'computed_at',
        'superseded_at',
    ];

    protected $casts = [
        'total_obtained' => 'decimal:2',
        'total_max' => 'decimal:2',
        'percentage' => 'decimal:2',
        'revaluation_applied' => 'boolean',
        'computed_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];
}
