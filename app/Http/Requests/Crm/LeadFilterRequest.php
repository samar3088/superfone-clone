<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the Leads screen's query string, for both the table and the export.
 *
 * These arrive from a URL, which anyone can hand-edit, so a bad date or a
 * non-numeric id should be a clear error rather than a silently empty table or
 * a database exception.
 */
class LeadFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:60'],
            'stage' => ['nullable', 'integer', 'exists:lead_stages,id'],
            // "unassigned" is a state, not an id, so it is allowed alongside one.
            'member' => ['nullable', Rule::when(
                $this->input('member') !== 'unassigned',
                ['integer', 'exists:users,id'],
                ['in:unassigned'],
            )],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'unread' => ['nullable', 'in:1,0,true,false'],
            'sort' => ['nullable', 'string', 'max:40'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_to.after_or_equal' => 'The end date cannot be before the start date.',
        ];
    }
}
