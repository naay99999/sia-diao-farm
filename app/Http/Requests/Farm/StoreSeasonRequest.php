<?php

namespace App\Http\Requests\Farm;

use App\Models\Season;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreSeasonRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'started_on' => ['required', 'date'],
            'ended_on' => ['nullable', 'date', 'after_or_equal:started_on'],
            'notes' => ['nullable', 'string'],
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

            $this->validateAgainstOtherSeasons($validator);
        }];
    }

    private function validateAgainstOtherSeasons(Validator $validator): void
    {
        $season = $this->route('season');
        $orchardId = $season instanceof Season
            ? $season->orchard_id
            : $this->route('orchard')->id;

        $startedOn = $this->date('started_on')->toDateString();
        $endedOn = $this->filled('ended_on') ? $this->date('ended_on')->toDateString() : null;

        $others = Season::query()
            ->where('orchard_id', $orchardId)
            ->when($season instanceof Season, fn ($query) => $query->whereKeyNot($season->getKey()))
            ->get();

        if ($endedOn === null && $others->contains(fn (Season $other) => $other->isOpen())) {
            $validator->errors()->add('started_on', 'มีรอบที่ยังไม่ปิดอยู่แล้ว');

            return;
        }

        $overlaps = $others->contains(function (Season $other) use ($startedOn, $endedOn): bool {
            $otherEnd = $other->ended_on?->toDateString();

            return ($endedOn === null || $other->started_on->toDateString() <= $endedOn)
                && ($otherEnd === null || $otherEnd >= $startedOn);
        });

        if ($overlaps) {
            $validator->errors()->add('started_on', 'ช่วงวันซ้อนกับรอบอื่น');
        }
    }
}
