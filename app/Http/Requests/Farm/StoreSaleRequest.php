<?php

namespace App\Http\Requests\Farm;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
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
            'buyer_name' => ['required', 'string', 'max:255'],
            'sold_on' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.grade_id' => ['required', 'exists:grades,id'],
            'items.*.weight_kg' => ['required', 'numeric', 'min:0.01'],
            'items.*.price_per_kg' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
