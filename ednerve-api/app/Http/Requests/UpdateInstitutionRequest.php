<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstitutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Platform Admin gate: RequiresAccountType + platform middleware.
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'institution_type' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
        ];
    }
}
