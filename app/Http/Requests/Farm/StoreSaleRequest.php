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
            'sold_on' => ['required', 'date'],
            'buyer_name' => ['nullable', 'string', 'max:255'],
            'weight_kg' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
