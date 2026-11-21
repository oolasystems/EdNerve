<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdmissionIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Institution Admin gate: RequiresAccountType + institution middleware.
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'programme_id' => [
                'required',
                'integer',
                Rule::exists('programmes', 'id')
                    ->where('institution_id', $this->user()->institution_id),
            ],
            'academic_session_id' => [
                'required',
                'integer',
                Rule::exists('academic_sessions', 'id')
                    ->where('institution_id', $this->user()->institution_id),
            ],
            'open_date' => ['required', 'date'],
            'close_date' => ['required', 'date', 'after_or_equal:open_date'],
            'status' => ['sometimes', Rule::in(['open', 'closed'])],
            'application_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'seat_capacity' => ['required', 'integer', 'min:1', 'max:2000'],
        ];
    }
}
