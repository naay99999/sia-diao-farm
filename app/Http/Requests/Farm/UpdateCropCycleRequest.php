<?php

namespace App\Http\Requests\Farm;

use App\Enums\CropCycleStage;
use App\Enums\CropCycleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateCropCycleRequest extends FormRequest
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
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'stage' => ['sometimes', new Enum(CropCycleStage::class)],
            'status' => ['sometimes', new Enum(CropCycleStatus::class)],
            'flowering_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
