<?php

namespace App\Http\Requests\Farm;

use App\Enums\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
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
            'season_id' => ['nullable', 'integer', 'exists:seasons,id'],
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'spent_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
