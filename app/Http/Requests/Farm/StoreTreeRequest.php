<?php

namespace App\Http\Requests\Farm;

use App\Models\Orchard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTreeRequest extends FormRequest
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
        /** @var Orchard $orchard */
        $orchard = $this->route('orchard');

        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('trees', 'code')->where('orchard_id', $orchard->id),
            ],
            'variety' => ['nullable', 'string', 'max:255'],
            'planted_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
