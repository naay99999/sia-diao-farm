<?php

namespace App\Http\Requests\Farm;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlotRequest extends FormRequest
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
            'fruit_variety_id' => ['required', 'exists:fruit_varieties,id'],
            'tree_count' => ['required', 'integer', 'min:1'],
            'planted_at' => ['nullable', 'date'],
            'area_rai' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
