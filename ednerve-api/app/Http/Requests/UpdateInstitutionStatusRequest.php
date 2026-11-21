<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInstitutionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Platform Admin gate: RequiresAccountType + platform middleware.
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ];
    }
}
