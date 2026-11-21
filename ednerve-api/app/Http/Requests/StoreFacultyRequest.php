<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFacultyRequest extends FormRequest
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
                'max:50',
                Rule::unique('faculties', 'code')
                    ->where('institution_id', $this->user()->institution_id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
