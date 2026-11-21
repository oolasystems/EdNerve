<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Institution Admin gate: RequiresAccountType + institution middleware.
    }

    public function rules(): array
    {
        return [
            'school_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('schools', 'id')
                    ->where('institution_id', $this->user()->institution_id),
            ],
            'faculty_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('faculties', 'id')
                    ->where('institution_id', $this->user()->institution_id),
            ],
            'unit_type' => [
                'sometimes',
                'required',
                Rule::in(['department', 'centre', 'office', 'division', 'other']),
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('academic_units', 'code')
                    ->where('institution_id', $this->user()->institution_id)
                    ->ignore((int) $this->route('id')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
