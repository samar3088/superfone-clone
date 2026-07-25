<?php

namespace App\Http\Requests\Settings;

use App\Support\LeadProviders;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::INTEGRATION_MANAGE) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            // Only providers with a working lead source may be connected.
            'provider' => ['required', Rule::in(LeadProviders::availableKeys())],
            'external_page_id' => ['required', 'string', 'max:64'],
            'external_form_id' => ['required', 'string', 'max:64'],
            'form_name' => ['required', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'provider.in' => 'That provider is not available yet.',
            'external_page_id.required' => 'Choose a page.',
            'external_form_id.required' => 'Choose a lead form.',
        ];
    }
}
