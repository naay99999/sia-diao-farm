<?php

namespace App\Http\Requests\Settings;

use App\Enums\FindingKind;
use App\Models\Finding;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FindingRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $finding = $this->route('finding');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('findings', 'name')->ignore($finding instanceof Finding ? $finding->id : null),
            ],
            'kind' => ['required', Rule::enum(FindingKind::class)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $finding = $this->route('finding');

            if (
                $finding instanceof Finding
                && ! $validator->errors()->has('kind')
                && $this->string('kind')->toString() !== $finding->kind->value
                && $finding->isInUse()
            ) {
                $validator->errors()->add('kind', 'เปลี่ยนชนิดไม่ได้ เพราะมีบันทึกใช้อยู่');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'ชื่อ',
            'kind' => 'ชนิด',
        ];
    }
}
