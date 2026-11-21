<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdmissionIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Institution Admin gate: RequiresAccountType + institution middleware.
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'programme_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('programmes', 'id')
                    ->where('institution_id', $this->user()->institution_id),
            ],
            'academic_session_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('academic_sessions', 'id')
                    ->where('institution_id', $this->user()->institution_id),
            ],
            'open_date' => ['sometimes', 'required', 'date'],
            'close_date' => ['sometimes', 'required', 'date', 'after_or_equal:open_date'],
            'status' => ['sometimes', Rule::in(['open', 'closed'])],
            'application_fee' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999999'],
            'seat_capacity' => ['sometimes', 'required', 'integer', 'min:1', 'max:2000'],
        ];
    }
}
