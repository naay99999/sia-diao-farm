<?php

namespace App\Http\Requests\Farm;

use App\Models\Tree;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTreeRequest extends FormRequest
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
        /** @var Tree $tree */
        $tree = $this->route('tree');

        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('trees', 'code')
                    ->where('orchard_id', $tree->orchard_id)
                    ->ignore($tree->id),
            ],
            'variety' => ['nullable', 'string', 'max:255'],
            'planted_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
