<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FarmNoteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge(['body' => trim($this->input('body'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'finding_id' => ['nullable', 'integer', Rule::exists('findings', 'id')],
            'tree_id' => ['nullable', 'integer', Rule::exists('trees', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => 'ข้อความ',
            'finding_id' => 'สิ่งที่พบ',
            'tree_id' => 'ต้น',
        ];
    }
}
