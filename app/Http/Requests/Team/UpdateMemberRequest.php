<?php

namespace App\Http\Requests\Team;

use App\Support\Permissions;
use App\Support\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::MEMBER_UPDATE) ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('member')->id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'mobile' => [
                'required', 'string', 'regex:/^[6-9]\d{9}$/',
                Rule::unique('users', 'mobile')->ignore($id)->whereNull('deleted_at'),
            ],
            'role' => ['required', Rule::in(Roles::ALL)],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => 'Enter a valid 10-digit mobile number.',
            'mobile.unique' => 'A team member with this mobile number already exists.',
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
            'name' => trim((string) $this->input('name')),
            'email' => trim((string) $this->input('email')),
        ]);
    }
}
