<?php

namespace App\Http\Requests\Settings;

use App\Support\LeadProviders;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIntegrationSettingsRequest extends FormRequest
{
    private const UNITS = ['seconds', 'minutes', 'hours', 'days'];

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

            ...$this->ruleBlock('new'),
            ...$this->ruleBlock('existing'),
        ];
    }

    /**
     * The New Lead and Existing Lead blocks are the same shape, so their rules
     * are generated rather than written out twice and drifting apart.
     *
     * @return array<string, array<int, mixed>>
     */
    private function ruleBlock(string $which): array
    {
        $p = $which === 'new' ? '' : 'existing_';
        $todo = "{$p}todo_enabled";
        $notify = "{$p}notify_enabled";

        return [
            $which === 'new' ? 'new_lead_enabled' : 'existing_lead_enabled' => ['boolean'],

            $todo => ['boolean'],
            "{$p}todo_type" => ['nullable', "required_if:{$todo},true", Rule::in(LeadProviders::todoTypes())],
            "{$p}todo_title" => ['nullable', "required_if:{$todo},true", 'string', 'max:150'],
            "{$p}todo_due_value" => ['nullable', "required_if:{$todo},true", 'integer', 'min:1', 'max:9999'],
            "{$p}todo_due_unit" => ['nullable', "required_if:{$todo},true", Rule::in(self::UNITS)],

            $notify => ['boolean'],
            "{$p}notify_value" => ['nullable', "required_if:{$notify},true", 'integer', 'min:0', 'max:9999'],
            "{$p}notify_unit" => ['nullable', "required_if:{$notify},true", Rule::in(self::UNITS)],
        ];
    }

    public function messages(): array
    {
        return [
            'source_type.required' => 'Choose where these leads come from.',
            'source.required' => 'Name the campaign or subcategory, e.g. "Summer Campaign".',
            'lead_stage_id.required' => 'Pick the stage new leads should start on.',

            'todo_type.required_if' => 'Choose a task type for the new-lead to-do.',
            'todo_title.required_if' => 'Give the new-lead to-do a title.',
            'todo_due_value.required_if' => 'Set when the new-lead task is due.',
            'todo_due_unit.required_if' => 'Choose a time unit.',
            'notify_value.required_if' => 'Set how long after arrival to notify.',
            'notify_unit.required_if' => 'Choose a time unit.',

            'existing_todo_type.required_if' => 'Choose a task type for the existing-lead to-do.',
            'existing_todo_title.required_if' => 'Give the existing-lead to-do a title.',
            'existing_todo_due_value.required_if' => 'Set when the existing-lead task is due.',
            'existing_todo_due_unit.required_if' => 'Choose a time unit.',
            'existing_notify_value.required_if' => 'Set how long after arrival to notify.',
            'existing_notify_unit.required_if' => 'Choose a time unit.',
        ];
    }

    /**
     * Clear the detail of any rule that is switched off.
     *
     * Without this, unticking "create a to-do" would leave its old type, title
     * and due time on the record — invisible on screen, but still there to be
     * acted on if the box were ever ticked again.
     */
    protected function prepareForValidation(): void
    {
        foreach (['', 'existing_'] as $p) {
            $parentOff = ! $this->boolean($p === '' ? 'new_lead_enabled' : 'existing_lead_enabled');

            if ($parentOff || ! $this->boolean("{$p}todo_enabled")) {
                $this->merge([
                    "{$p}todo_enabled" => $parentOff ? false : $this->boolean("{$p}todo_enabled"),
                    "{$p}todo_type" => null,
                    "{$p}todo_title" => null,
                    "{$p}todo_due_value" => null,
                    "{$p}todo_due_unit" => null,
                ]);
            }

            if ($parentOff || ! $this->boolean("{$p}notify_enabled")) {
                $this->merge([
                    "{$p}notify_enabled" => $parentOff ? false : $this->boolean("{$p}notify_enabled"),
                    "{$p}notify_value" => null,
                    "{$p}notify_unit" => null,
                ]);
            }
        }
    }
}
