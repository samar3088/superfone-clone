<?php

namespace App\Http\Requests\Crm;

use App\Support\LeadProviders;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],

            /*
             | Uniqueness is deliberately not checked here. A number that
             | already belongs to someone is not an error — it means this is
             | that person, and the service returns them instead of creating a
             | second record.
             */
            'phones' => ['required', 'array', 'min:1', 'max:10'],
            'phones.*' => ['nullable', 'string', 'max:20'],
            'emails' => ['array', 'max:10'],
            'emails.*' => ['nullable', 'email:rfc', 'max:150'],

            'website' => ['nullable', 'string', 'max:191'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'house_no' => ['nullable', 'string', 'max:60'],
            'city' => ['nullable', 'string', 'max:100'],
            'address_1' => ['nullable', 'string', 'max:191'],
            'address_2' => ['nullable', 'string', 'max:191'],
            'additional_info' => ['nullable', 'string', 'max:2000'],

            // Lead tracking — only acted on when a stage is chosen.
            'source' => ['nullable', 'string', 'max:120'],
            'source_type' => ['nullable', Rule::in(LeadProviders::sourceTypes())],
            'deal_value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lead_group_id' => ['nullable', 'exists:lead_groups,id'],
            'campaign' => ['nullable', 'string', 'max:150'],
            'lead_stage_id' => ['nullable', 'exists:lead_stages,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],

            'create_task' => ['boolean'],
            'task_note' => ['nullable', 'string', 'max:2000'],
            'task_type' => ['nullable', 'required_if:create_task,true', Rule::in(LeadProviders::todoTypes())],
            'task_due_at' => ['nullable', 'required_if:create_task,true', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'phones.required' => 'A contact needs at least one phone number.',
            'task_type.required_if' => 'Choose a task type.',
            'task_due_at.required_if' => 'Set when the task is due.',
        ];
    }

    /**
     * A task cannot be assigned without a lead to hang it on, and a lead only
     * exists once a stage is chosen — so clear the task rather than let the
     * form promise something that will not appear.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->boolean('create_task') || blank($this->input('lead_stage_id'))) {
            $this->merge([
                'create_task' => false,
                'task_note' => null,
                'task_type' => null,
                'task_due_at' => null,
            ]);
        }
    }
}
