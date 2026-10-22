<?php

namespace App\Http\Requests\Farm;

use App\Enums\Severity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateObservationRequest extends FormRequest
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
            'observed_on' => ['required', 'date'],
            'severity' => ['nullable', Rule::enum(Severity::class)],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'observed_on' => 'วันที่พบ',
            'severity' => 'ความรุนแรง',
            'notes' => 'หมายเหตุ',
        ];
    }
}
