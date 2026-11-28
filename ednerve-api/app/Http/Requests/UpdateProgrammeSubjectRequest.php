<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProgrammeSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Institution Admin gate: RequiresAccountType + institution middleware.
    }

    public function rules(): array
    {
        return [
            'semester' => ['sometimes', 'required', 'integer', 'min:1', 'max:20'],
            'requirement_type' => ['sometimes', Rule::in(['core', 'elective'])],
            'position' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
