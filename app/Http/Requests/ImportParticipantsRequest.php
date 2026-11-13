<?php

namespace App\Http\Requests;

use App\Models\Participant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // access is already controlled by the staff PIN
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120', 'extensions:xlsx,csv,txt'],
            'category' => ['required', Rule::in(array_keys(Participant::CATEGORIES))],
            'partner_name' => ['nullable', 'string', 'max:120'],
            'skip_duplicates' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Choose a file to import.',
            'file.extensions' => 'Upload a .xlsx or .csv file. For an old .xls file, open it in Excel and "Save as" .xlsx.',
            'file.max' => 'The file is too large (maximum 5 MB).',
        ];
    }
}