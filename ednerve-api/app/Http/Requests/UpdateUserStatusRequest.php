<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Platform Admin gate: platform middleware + RequiresAccountType.
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
