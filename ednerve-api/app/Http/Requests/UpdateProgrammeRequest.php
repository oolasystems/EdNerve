<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProgrammeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Institution Admin gate: RequiresAccountType + institution middleware.
    }

    public function rules(): array
    {
        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('programmes', 'code')
                    ->where('institution_id', $this->user()->institution_id)
                    ->ignore((int) $this->route('id')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'level' => ['sometimes', 'required', Rule::in(['UG', 'PG'])],
            'duration_semesters' => ['sometimes', 'required', 'integer', 'min:1', 'max:20'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
