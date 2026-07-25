<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $id = $this->user()->id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'mobile' => [
                'required', 'string', 'regex:/^[6-9]\d{9}$/',
                Rule::unique('users', 'mobile')->ignore($id)->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => 'Enter a valid 10-digit mobile number.',
            'mobile.unique' => 'Another account already uses this mobile number.',
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
            'email' => trim((string) $this->input('email')),
            'name' => trim((string) $this->input('name')),
        ]);
    }
}
