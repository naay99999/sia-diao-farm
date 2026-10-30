<?php

namespace App\Http\Requests\Farm;

use App\Models\Tree;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreHarvestRequest extends FormRequest
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
            'harvested_on' => ['required', 'date'],
            'weight_kg' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'fruit_count' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var Tree $tree */
            $tree = $this->route('tree');

            if ($tree->isRemoved()) {
                $validator->errors()->add('tree', 'ต้นไม้นี้ไม่อยู่แล้ว บันทึกผลผลิตไม่ได้');
            }
        }];
    }
}
