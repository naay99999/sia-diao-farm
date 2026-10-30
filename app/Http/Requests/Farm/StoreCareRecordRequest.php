<?php

namespace App\Http\Requests\Farm;

use App\Enums\CareType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCareRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CareType::class)],
            'performed_on' => ['required', 'date'],
            'product' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'tree_ids' => ['required', 'array', 'min:1'],
            'tree_ids.*' => ['integer', 'distinct', 'exists:trees,id'],
            'issue_ids' => ['nullable', 'array'],
            'issue_ids.*' => ['integer', 'distinct', 'exists:tree_issues,id'],
            'return_to' => ['nullable', Rule::in(['tree', 'orchard'])],
        ];
    }
}
