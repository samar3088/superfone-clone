<?php

namespace App\Http\Requests\Crm;

use App\Support\LeadProviders;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Step two of the contact import: what to do with a file already checked.
 *
 * The file itself is not resubmitted — only the token standing for it, which
 * ContactImportService resolves under the person who uploaded it.
 */
class RunImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'uuid'],

            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'lead_stage_id' => ['nullable', 'integer', 'exists:lead_stages,id'],
            'lead_group_id' => ['nullable', 'integer', 'exists:lead_groups,id'],

            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],

            'assign_to' => ['nullable', 'array'],
            'assign_to.*' => ['integer', 'exists:users,id'],

            'update_existing' => ['boolean'],
            'skip_phone_check' => ['boolean'],

            /*
             | A to-do needs a lead to hang off, and a lead is only created when
             | a stage is chosen. Asking for one without the other would produce
             | nothing and say nothing, so it is refused with a reason.
             */
            'create_task' => ['boolean'],
            'task_type' => [
                Rule::requiredIf(fn () => $this->boolean('create_task')),
                'nullable', 'string', Rule::in(LeadProviders::todoTypes()),
            ],
            'task_title' => ['nullable', 'string', 'max:150'],
            'task_due_at' => [
                Rule::requiredIf(fn () => $this->boolean('create_task')),
                'nullable', 'date',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if ($this->boolean('create_task') && blank($this->input('lead_stage_id'))) {
                    $validator->errors()->add(
                        'lead_stage_id',
                        'Choose a lead stage as well — a to-do is raised against a lead, and without a stage no lead is created.',
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'That upload has expired. Choose the file again.',
            'task_type.required' => 'Choose what kind of to-do to raise.',
            'task_due_at.required' => 'Give the to-do a due date.',
        ];
    }
}
