<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFacultyRequest extends FormRequest
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
                Rule::unique('faculties', 'code')
                    ->where('institution_id', $this->user()->institution_id)
                    ->ignore((int) $this->route('id')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
