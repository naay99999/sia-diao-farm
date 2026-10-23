<?php

namespace App\Support;

use App\Models\CareRecord;
use App\Models\Harvest;
use App\Models\Observation;
use App\Models\ObservationPhoto;
use App\Models\Tree;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

class TreeTimeline
{
    /** How long the photo links stay valid. */
    public const PHOTO_URL_MINUTES = 30;

    /**
     * Newest first. Every entry carries `fromAi` and `aiBy`; observation entries also carry short-lived photo URLs.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function for(Tree $tree, int $limit = 50): array
    {
        $care = $tree->careRecords()->with(['trees:id,code,removed_on', 'issues:id', 'creator:id,name', 'updater:id,name'])->get()->map(fn (CareRecord $record): array => [
            'kind' => 'care',
            'id' => $record->id,
            'date' => $record->performed_on->toDateString(),
            'title' => trim($record->type->label().' '.$record->product),
            'detail' => collect([$record->quantity, $record->notes])->filter()->join(' · ') ?: null,
            'fromAi' => $record->isFromAi(),
            'aiBy' => $record->aiActorName(),
            'record' => [
                'id' => $record->id,
                'type' => $record->type->value,
                'performed_on' => $record->performed_on->toDateString(),
                'product' => $record->product,
                'quantity' => $record->quantity,
                'notes' => $record->notes,
                'trees' => $record->trees->map->only(['id', 'code', 'removed_on'])->all(),
                'issue_ids' => $record->issues->pluck('id')->all(),
            ],
        ]);

        $harvests = $tree->harvests()->with(['creator:id,name', 'updater:id,name'])->get()->map(fn (Harvest $harvest): array => [
            'kind' => 'harvest',
            'id' => $harvest->id,
            'date' => $harvest->harvested_on->toDateString(),
            'title' => 'ผลผลิต '.(float) $harvest->weight_kg.' กก.',
            'detail' => collect([
                $harvest->fruit_count ? $harvest->fruit_count.' ผล' : null,
                $harvest->notes,
            ])->filter()->join(' · ') ?: null,
            'fromAi' => $harvest->isFromAi(),
            'aiBy' => $harvest->aiActorName(),
            'record' => [
                'id' => $harvest->id,
                'harvested_on' => $harvest->harvested_on->toDateString(),
                'weight_kg' => $harvest->weight_kg,
                'fruit_count' => $harvest->fruit_count,
                'notes' => $harvest->notes,
            ],
        ]);

        $observations = $tree->observations()->with(['finding', 'creator:id,name', 'updater:id,name', 'photos' => fn ($query) => $query->orderBy('id')])->get()->map(fn (Observation $observation): array => [
            'kind' => 'observation',
            'id' => $observation->id,
            'date' => $observation->observed_on->toDateString(),
            'title' => self::observationTitle($observation),
            'detail' => collect([$observation->severity?->label(), $observation->notes])->filter()->join(' · ') ?: null,
            'fromAi' => $observation->isFromAi(),
            'aiBy' => $observation->aiActorName(),
            'photos' => self::photoUrls($observation),
            'record' => [
                'id' => $observation->id,
                'finding_id' => $observation->finding_id,
                'tree_issue_id' => $observation->tree_issue_id,
                'observed_on' => $observation->observed_on->toDateString(),
                'severity' => $observation->severity?->value,
                'outcome' => $observation->outcome?->value,
                'notes' => $observation->notes,
            ],
        ]);

        return $care->concat($harvests)->concat($observations)
            ->sortBy([['date', 'desc'], ['id', 'desc']])
            ->take($limit)
            ->values()
            ->all();
    }

    private static function observationTitle(Observation $observation): string
    {
        $name = $observation->finding->name;

        if ($observation->outcome === null) {
            return $name;
        }

        return "ดูซ้ำ: {$name} — {$observation->outcome->label()}";
    }

    /**
     * @return array<int, string>
     */
    private static function photoUrls(Observation $observation): array
    {
        $disk = Storage::disk('photos');
        $expiresAt = Date::now()->addMinutes(self::PHOTO_URL_MINUTES);

        return $observation->photos
            ->map(fn (ObservationPhoto $photo): string => $disk->temporaryUrl($photo->path, $expiresAt))
            ->all();
    }
}
