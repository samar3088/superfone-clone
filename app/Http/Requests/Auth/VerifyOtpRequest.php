<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'code' => ['required', 'string', 'digits:'.config('otp.length')],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => 'Enter a valid 10-digit mobile number.',
            'code.digits' => 'The code is '.config('otp.length').' digits.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $digits = preg_replace('/\D/', '', (string) $this->input('mobile'));

        if (strlen((string) $digits) > 10) {
            $digits = substr((string) $digits, -10);
        }

        $this->merge([
            'mobile' => $digits,
            'code' => preg_replace('/\D/', '', (string) $this->input('code')),
        ]);
    }
}
