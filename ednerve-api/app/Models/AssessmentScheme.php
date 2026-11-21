<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentScheme extends Model
{
    protected $fillable = ['institution_id', 'code', 'name', 'scope', 'subject_id', 'programme_id', 'description', 'status'];

    public function components(): HasMany
    {
        return $this->hasMany(AssessmentSchemeComponent::class, 'scheme_id')->orderBy('position')->orderBy('id');
    }
}
