<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class ImportContactsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            /*
             | Extensions rather than mime types: a CSV saved by Excel arrives
             | as anything from text/plain to application/vnd.ms-excel depending
             | on the machine, and rejecting a valid file on that basis is a
             | support call nobody can diagnose.
             */
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Choose a file to import.',
            'file.mimes' => 'Save the sheet as CSV and upload that — .xlsx is not read directly.',
            'file.max' => 'That file is over 5 MB. Split it and import in parts.',
        ];
    }
}
