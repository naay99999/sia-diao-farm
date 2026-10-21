<?php

namespace App\Http\Requests\Farm;

use App\Models\Orchard;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreTreeBatchRequest extends FormRequest
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
            'prefix' => [
                'required', 'string', 'max:40',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->integer('count') < 1 || $this->integer('start') < 1) {
                        return;
                    }

                    /** @var Orchard $orchard */
                    $orchard = $this->route('orchard');

                    $existing = $orchard->trees()
                        ->whereIn('code', $this->codes())
                        ->pluck('code');

                    if ($existing->isNotEmpty()) {
                        $fail('รหัสต้นไม้ซ้ำกับที่มีอยู่แล้ว: '.$existing->join(', '));
                    }
                },
            ],
            'start' => ['required', 'integer', 'min:1'],
            'count' => ['required', 'integer', 'min:1', 'max:500'],
            'variety' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        $prefix = (string) $this->input('prefix');
        $start = $this->integer('start');

        return array_map(
            fn (int $number): string => $prefix.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            range($start, $start + min(max($this->integer('count'), 1), 500) - 1),
        );
    }
}
