<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RequestOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Indian mobile numbers: 10 digits, never starting 0–5.
            'mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => 'Enter a valid 10-digit mobile number.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Accept pasted numbers with +91, spaces or dashes.
        $digits = preg_replace('/\D/', '', (string) $this->input('mobile'));

        if (strlen((string) $digits) > 10) {
            $digits = substr((string) $digits, -10);
        }

        $this->merge(['mobile' => $digits]);
    }
}
