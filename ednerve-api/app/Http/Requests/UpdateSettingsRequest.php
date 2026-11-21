<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Platform Admin gate: platform middleware + RequiresAccountType.
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'min:1', 'max:50'],
            'settings.*.key' => ['required', 'string', 'max:120', 'exists:platform_settings,key'],
            // Type-specific validation happens in the controller (types live in the DB).
            'settings.*.value' => ['present'],
        ];
    }
}
