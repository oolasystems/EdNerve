<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Institution Admin gate: RequiresAccountType + institution middleware.
    }

    public function rules(): array
    {
        return [
            'faculty_id' => [
                'nullable',
                'integer',
                Rule::exists('faculties', 'id')
                    ->where('institution_id', $this->user()->institution_id),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('schools', 'code')
                    ->where('institution_id', $this->user()->institution_id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
