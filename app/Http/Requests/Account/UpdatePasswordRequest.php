<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Only required for users who already have a password set; an
            // OTP-only account is setting one for the first time.
            'current_password' => [
                $this->user()->password ? 'required' : 'nullable',
                'current_password',
            ],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'That is not your current password.',
        ];
    }
}
