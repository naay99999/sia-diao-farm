<?php

namespace App\Http\Requests\Farm;

use Illuminate\Foundation\Http\FormRequest;

class StoreActivityRequest extends FormRequest
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
            'activity_type_id' => ['required', 'exists:activity_types,id'],
            'performed_on' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'expense_category_id' => ['nullable', 'required_with:cost', 'exists:expense_categories,id'],
        ];
    }
}
