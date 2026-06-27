<?php

namespace App\Http\Requests\Farm;

use Illuminate\Foundation\Http\FormRequest;

class StoreFruitVarietyRequest extends FormRequest
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
            'fruit_type_id' => ['required', 'exists:fruit_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'days_to_harvest' => ['required', 'integer', 'min:1'],
        ];
    }
}
