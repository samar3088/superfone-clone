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
            'new_lead_email' => ['required', 'boolean'],
        ];
    }
}
