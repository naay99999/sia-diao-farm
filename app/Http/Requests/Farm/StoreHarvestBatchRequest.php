<?php

namespace App\Http\Requests\Farm;

use App\Models\Orchard;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreHarvestBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Drop rows without a weight so only filled rows are validated and saved.
     */
    protected function prepareForValidation(): void
    {
        $rows = $this->input('rows');

        if (! is_array($rows)) {
            return;
        }

        $this->merge([
            'rows' => array_values(array_filter(
                $rows,
                fn (mixed $row): bool => is_array($row)
                    && isset($row['weight_kg'])
                    && trim((string) $row['weight_kg']) !== '',
            )),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'harvested_on' => ['required', 'date'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.tree_id' => ['required', 'integer', 'distinct'],
            'rows.*.weight_kg' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'rows.*.fruit_count' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.required' => 'กรอกน้ำหนักอย่างน้อย 1 ต้น',
            'rows.min' => 'กรอกน้ำหนักอย่างน้อย 1 ต้น',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Orchard $orchard */
            $orchard = $this->route('orchard');
            $rows = $this->validated('rows');

            $validIds = $orchard->trees()
                ->active()
                ->whereIn('id', array_column($rows, 'tree_id'))
                ->pluck('id')
                ->all();

            foreach ($rows as $index => $row) {
                if (! in_array((int) $row['tree_id'], $validIds, true)) {
                    $validator->errors()->add("rows.$index.tree_id", 'ต้นไม้ไม่อยู่ในสวนนี้หรือไม่อยู่แล้ว');
                }
            }
        }];
    }
}
