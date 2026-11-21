<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
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
                'max:120',
                'regex:/^[a-z][a-z0-9_.]{1,119}$/',
                Rule::unique('permissions', 'code'),
            ],
            'name' => ['required', 'string', 'max:150'],
            'module' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_-]{1,59}$/'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
