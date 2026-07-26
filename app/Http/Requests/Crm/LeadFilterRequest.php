<?php

namespace App\Http\Requests\Crm;

use App\Support\FilterList;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

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
            /*
             | Comma-joined lists from the multi-select pickers. Ids are not
             | checked against the tables here: an id that no longer exists just
             | matches nothing, and rejecting the whole request would strand
             | anyone holding a bookmark to a since-deleted stage or member.
             */
            'stage' => ['nullable', 'string', 'max:200', $this->listRule()],
            // "unassigned" is a state rather than an id, so it is allowed too.
            'member' => ['nullable', 'string', 'max:200', $this->listRule(['unassigned'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'unread' => ['nullable', 'in:1,0,true,false'],
            'kind' => ['nullable', 'in:fresh,existing'],
            'sort' => ['nullable', 'string', 'max:40'],
            'direction' => ['nullable', 'in:asc,desc'],
            // Any size the control does not offer is snapped to the default by
            // DataTableService rather than rejected — an old bookmark carrying
            // a since-retired size should still open the page.
            'per_page' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** Every entry must be a number, or one of the allowed words. */
    private function listRule(array $words = []): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($words) {
            if (! FilterList::isValid($value, $words)) {
                $fail('That filter value is not valid.');
            }
        };
    }

    public function messages(): array
    {
        return [
            'date_to.after_or_equal' => 'The end date cannot be before the start date.',
        ];
    }
}
