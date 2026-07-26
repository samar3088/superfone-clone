<?php

namespace App\Http\Requests\Crm;

use App\Support\FilterList;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** Validates the Customers screen's query string, for the table and the export. */
class CustomerFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            // Comma-joined list from the multi-select picker.
            'member' => ['nullable', 'string', 'max:200', function (string $a, mixed $v, Closure $fail) {
                if (! FilterList::isValid($v)) {
                    $fail('That filter value is not valid.');
                }
            }],
            'leads' => ['nullable', 'in:with,without'],

            // Comma-joined lists from the advanced filter panel.
            ...collect(['team', 'tags', 'stage', 'group', 'creator'])
                ->mapWithKeys(fn (string $key) => [$key => [
                    'nullable', 'string', 'max:200',
                    function (string $a, mixed $v, Closure $fail) {
                        if (! FilterList::isValid($v)) {
                            $fail('That filter value is not valid.');
                        }
                    },
                ]])->all(),
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['nullable', 'string', 'max:40'],
            'direction' => ['nullable', 'in:asc,desc'],
            // Any size the control does not offer is snapped to the default by
            // DataTableService rather than rejected — an old bookmark carrying
            // a since-retired size should still open the page.
            'per_page' => ['nullable', 'integer'],
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
