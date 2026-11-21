<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Institution Admin gate: RequiresAccountType + institution middleware.
    }

    public function rules(): array
    {
        return [
            'role_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('roles', 'id')->where('status', 'active'),
            ],
            'academic_unit_id' => ['sometimes', 'nullable', 'integer'],
            'is_primary' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:starts_at',
            ],
        ];
    }
}
