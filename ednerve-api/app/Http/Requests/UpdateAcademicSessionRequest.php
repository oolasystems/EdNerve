<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicSessionRequest extends FormRequest
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
                'max:30',
                Rule::unique('academic_sessions', 'code')
                    ->where('institution_id', $this->user()->institution_id)
                    ->ignore((int) $this->route('id')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
