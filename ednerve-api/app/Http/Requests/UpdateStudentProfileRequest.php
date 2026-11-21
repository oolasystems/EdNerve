<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Institution Admin gate: RequiresAccountType + institution middleware.
    }

    public function rules(): array
    {
        return [
            'programme_id'         => ['sometimes', 'nullable', 'integer'],
            'academic_session_id'  => ['sometimes', 'nullable', 'integer'],
            'enrollment_status'    => ['sometimes', Rule::in(['current', 'leave', 'withheld', 'graduated', 'terminated'])],
            'admitted_on'          => ['sometimes', 'nullable', 'date'],
            'left_on'              => ['sometimes', 'nullable', 'date', 'after_or_equal:admitted_on'],
            'father_name'          => ['sometimes', 'nullable', 'string', 'max:120'],
            'mother_name'          => ['sometimes', 'nullable', 'string', 'max:120'],
            'dob'                  => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender'               => ['sometimes', 'nullable', 'string', 'max:20'],
            'nationality'          => ['sometimes', 'nullable', 'string', 'max:40'],
            'category'             => ['sometimes', 'nullable', 'string', 'max:20'],
            'guardian_name'        => ['sometimes', 'nullable', 'string', 'max:120'],
            'guardian_relation'    => ['sometimes', 'nullable', 'string', 'max:40'],
            'guardian_phone'       => ['sometimes', 'nullable', 'string', 'max:30'],
            'address_line'         => ['sometimes', 'nullable', 'string'],
            'city'                 => ['sometimes', 'nullable', 'string', 'max:100'],
            'state'                => ['sometimes', 'nullable', 'string', 'max:100'],
            'pin_code'             => ['sometimes', 'nullable', 'string', 'max:12'],
            'remarks'              => ['sometimes', 'nullable', 'string'],
        ];
    }
}
