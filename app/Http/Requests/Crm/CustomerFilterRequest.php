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
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
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
