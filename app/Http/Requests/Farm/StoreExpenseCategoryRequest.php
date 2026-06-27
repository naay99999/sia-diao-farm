<?php

namespace App\Http\Requests\Farm;

use App\Enums\ExpenseScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreExpenseCategoryRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'default_scope' => ['required', new Enum(ExpenseScope::class)],
        ];
    }
}
