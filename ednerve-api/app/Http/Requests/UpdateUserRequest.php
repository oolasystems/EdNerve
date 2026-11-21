<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Institution Admin gate: RequiresAccountType + institution middleware.
    }

    public function rules(): array
    {
        $user = (int) $this->route('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],

            // Global email uniqueness.
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')
                    ->ignore($user),
            ],

            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],

            'password' => ['sometimes', 'required', 'string', 'min:8'],

            'status' => [
                'sometimes',
                Rule::in(['active', 'inactive']),
            ],
        ];
    }
}
