<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Platform Admin gate: platform middleware + RequiresAccountType.
    }

    public function rules(): array
    {
        return [
            'module' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
            'system' => ['nullable', Rule::in(['system', 'custom', 'all'])],
            'search' => ['nullable', 'string', 'max:120'],
        ];
    }
}
