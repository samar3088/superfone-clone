<?php

namespace App\Http\Requests\Settings;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFacebookTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::SETTINGS_MANAGE) ?? false;
    }

    public function rules(): array
    {
        return [
            /*
             | Graph tokens are long opaque strings. Length and character bounds
             | are all we can usefully assert — a paste that picked up
             | whitespace or a truncated copy is the common mistake, and both
             | fail here rather than silently breaking every sync.
             */
            'token' => ['required', 'string', 'min:50', 'max:500', 'regex:/^[A-Za-z0-9_\-\.]+$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['token' => trim((string) $this->input('token'))]);
    }

    public function messages(): array
    {
        return [
            'token.regex' => 'That does not look like a Facebook token — check for a stray space or a partial copy.',
            'token.min' => 'That token looks truncated. Copy the whole value from Meta.',
        ];
    }
}
