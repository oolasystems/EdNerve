<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Institution Admin gate: RequiresAccountType + institution middleware.
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('subjects', 'code')
                    ->where('institution_id', $this->user()->institution_id),
            ],
            'name' => ['required', 'string', 'max:200'],
            'credits' => ['sometimes', 'integer', 'min:0', 'max:30'],
            'subject_type' => ['sometimes', Rule::in(['theory', 'practical'])],
            'academic_unit_id' => ['nullable', 'integer', Rule::exists('academic_units', 'id')->where('institution_id', $this->user()->institution_id)],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
