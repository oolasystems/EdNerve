<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Institution Admin gate: RequiresAccountType + institution middleware.
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            // Email must be globally unique across all institutions.
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email'),
            ],

            'phone' => ['nullable', 'string', 'max:30'],

            'password' => ['required', 'string', 'min:8'],

            'status' => [
                'sometimes',
                Rule::in(['active', 'inactive']),
            ],
        ];
    }
}
