<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Institution Admin gate: RequiresAccountType + institution middleware.
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where('status', 'active'),
            ],
            'academic_unit_id' => ['nullable', 'integer'],
            'is_primary' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
