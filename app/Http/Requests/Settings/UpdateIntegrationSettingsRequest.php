<?php

namespace App\Http\Requests\Settings;

use App\Support\LeadProviders;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIntegrationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::INTEGRATION_MANAGE) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],

            // Source — where these leads come from.
            'source_type' => ['required', Rule::in(LeadProviders::sourceTypes())],
            'source' => ['required', 'string', 'max:120'],

            // Assignment — who owns them and how they are classified.
            'member_ids' => ['array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'lead_stage_id' => ['required', 'exists:lead_stages,id'],
            'lead_group_id' => ['nullable', 'exists:lead_groups,id'],
            'tag_ids' => ['array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],

            // New lead — what happens on arrival.
            'new_lead_enabled' => ['boolean'],
            'todo_enabled' => ['boolean'],
            'todo_type' => ['nullable', 'required_if:todo_enabled,true', Rule::in(LeadProviders::todoTypes())],
            'todo_title' => ['nullable', 'required_if:todo_enabled,true', 'string', 'max:150'],
            'todo_due_value' => ['nullable', 'required_if:todo_enabled,true', 'integer', 'min:1', 'max:9999'],
            'todo_due_unit' => ['nullable', 'required_if:todo_enabled,true', Rule::in(['seconds', 'minutes', 'hours', 'days'])],
            'notify_enabled' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'source_type.required' => 'Choose where these leads come from.',
            'source.required' => 'Name the campaign or subcategory, e.g. "Summer Campaign".',
            'lead_stage_id.required' => 'Pick the stage new leads should start on.',
            'todo_type.required_if' => 'Choose a task type for the to-do.',
            'todo_title.required_if' => 'Give the to-do a title.',
            'todo_due_value.required_if' => 'Set when the task is due.',
            'todo_due_unit.required_if' => 'Choose a time unit.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // A disabled to-do should not carry stale rule values.
        if (! $this->boolean('todo_enabled')) {
            $this->merge([
                'todo_type' => null,
                'todo_title' => null,
                'todo_due_value' => null,
                'todo_due_unit' => null,
            ]);
        }
    }
}
