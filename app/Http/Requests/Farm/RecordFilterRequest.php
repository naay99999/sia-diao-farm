<?php

namespace App\Http\Requests\Farm;

use Illuminate\Foundation\Http\FormRequest;

class RecordFilterRequest extends FormRequest
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
            'orchard_id' => ['nullable', 'integer', 'exists:orchards,id'],
            'season_id' => ['nullable', 'integer', 'exists:seasons,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
            'via' => ['nullable', 'in:app,mcp'],
        ];
    }

    /**
     * The filters in the shape the records pages return to the client.
     *
     * @return array{orchard_id: int|null, season_id: int|null, from: string|null, to: string|null, created_by: int|null, via: string|null}
     */
    public function filters(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        return [
            'orchard_id' => isset($data['orchard_id']) ? (int) $data['orchard_id'] : null,
            'season_id' => isset($data['season_id']) ? (int) $data['season_id'] : null,
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
            'created_by' => isset($data['created_by']) ? (int) $data['created_by'] : null,
            'via' => $data['via'] ?? null,
        ];
    }
}
