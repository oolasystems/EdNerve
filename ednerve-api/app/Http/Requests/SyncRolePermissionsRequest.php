<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Platform Admin gate: platform middleware + RequiresAccountType.
    }

    public function rules(): array
    {
        return [
            // Empty array is allowed — clears the role's permission mapping.
            'permission_ids' => ['present', 'array'],
            'permission_ids.*' => [
                'integer',
                Rule::exists('permissions', 'id')->where('status', 'active'),
            ],
        ];
    }
}
