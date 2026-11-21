<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInstitutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Platform Admin gate: RequiresAccountType + platform middleware.
    }

    public function rules(): array
    {
        return [
            'institution' => ['required', 'array'],
            'institution.code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('institutions', 'code'),
            ],
            'institution.name' => ['required', 'string', 'max:255'],
            'institution.slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('institutions', 'slug'),
            ],
            'institution.institution_type' => [
                'required',
                'string',
                'max:100',
            ],
            'institution.email' => ['nullable', 'email', 'max:255'],
            'institution.phone' => ['nullable', 'string', 'max:30'],
            'institution.website' => ['nullable', 'url', 'max:255'],
            'institution.address' => ['nullable', 'string', 'max:1000'],
            'institution.city' => ['nullable', 'string', 'max:100'],
            'institution.state' => ['nullable', 'string', 'max:100'],
            'institution.country' => ['required', 'string', 'max:100'],

            'initial_admin' => ['required', 'array'],
            'initial_admin.name' => [
                'required',
                'string',
                'max:255',
            ],
            'initial_admin.email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'initial_admin.phone' => [
                'nullable',
                'string',
                'max:30',
            ],
            'initial_admin.password' => [
                'required',
                'string',
                'min:8',
            ],
        ];
    }
}
