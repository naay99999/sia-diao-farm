<?php

namespace App\Http\Requests\Farm;

use App\Enums\CareType;
use App\Models\CareRecord;
use App\Models\Tree;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCareRecordRequest extends FormRequest
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
            'type' => ['required', Rule::enum(CareType::class)],
            'performed_on' => ['required', 'date'],
            'product' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'tree_ids' => ['required', 'array', 'min:1'],
            'tree_ids.*' => ['integer', 'distinct', 'exists:trees,id'],
            'return_to' => ['nullable', Rule::in(['tree', 'orchard'])],
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

            $orchardId = $this->orchardId();
            $ids = array_values($this->validated('tree_ids'));

            $validIds = Tree::query()
                ->where('orchard_id', $orchardId)
                ->active()
                ->whereIn('id', $ids)
                ->pluck('id')
                ->all();

            foreach ($ids as $index => $id) {
                if (! in_array($id, $validIds, false)) {
                    $validator->errors()->add("tree_ids.$index", 'ต้นไม้ไม่อยู่ในสวนนี้หรือไม่อยู่แล้ว');
                }
            }
        }];
    }

    private function orchardId(): int
    {
        $careRecord = $this->route('care_record');

        return $careRecord instanceof CareRecord
            ? $careRecord->orchard_id
            : $this->route('orchard')->id;
    }
}
