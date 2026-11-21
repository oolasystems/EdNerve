<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPlatformUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Platform Admin gate: platform middleware + RequiresAccountType.
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:150'],
            'account_type' => ['nullable', Rule::in(['platform', 'institution'])],
            'institution_id' => ['nullable', 'integer', 'exists:institutions,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['sometimes', Rule::in(['name', 'email', 'created_at', 'status'])],
            'sort_order' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
