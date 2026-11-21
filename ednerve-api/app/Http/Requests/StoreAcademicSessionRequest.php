<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAcademicSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Institution Admin gate: RequiresAccountType + institution middleware.
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('academic_sessions', 'code')
                    ->where('institution_id', $this->user()->institution_id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
