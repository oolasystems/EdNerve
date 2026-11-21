<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Platform Admin gate: platform middleware + RequiresAccountType.
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:60',
                'regex:/^[a-z][a-z0-9_-]{2,59}$/',
                Rule::unique('roles', 'code'),
            ],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => [
                'integer',
                Rule::exists('permissions', 'id')->where('status', 'active'),
            ],
        ];
    }
}
