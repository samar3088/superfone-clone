<?php

namespace App\Http\Requests\Settings;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::SETTINGS_MANAGE) ?? false;
    }

    public function rules(): array
    {
        return [
            'new_lead_email' => ['nullable', 'boolean'],
            // 0 turns repeat detection off entirely; the cap stops a typo
            // making every enquiry a repeat forever.
            'duplicate_window_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }
}
