<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexInstitutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Platform Admin gate: RequiresAccountType + platform middleware.
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'institution_type' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort_by' => [
                'sometimes',
                Rule::in(['name', 'code', 'created_at']),
            ],
            'sort_order' => [
                'sometimes',
                Rule::in(['asc', 'desc']),
            ],
        ];
    }
}
