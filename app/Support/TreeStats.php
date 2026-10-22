<?php

namespace App\Support;

use App\Enums\FindingKind;
use App\Models\Season;
use App\Models\Tree;

class TreeStats
{
    /**
     * @return array{finding: string, observedOn: string, inCurrentSeason: bool}|null
     */
    public static function latestStage(Tree $tree): ?array
    {
        $observation = $tree->observations()
            ->whereRelation('finding', 'kind', FindingKind::Stage)
            ->with('finding')
            ->orderByDesc('observed_on')
            ->orderByDesc('id')
            ->first();

        if ($observation === null) {
            return null;
        }

        $currentSeason = $tree->orchard->currentSeason;

        return [
            'finding' => $observation->finding->name,
            'observedOn' => $observation->observed_on->toDateString(),
            'inCurrentSeason' => $currentSeason?->containsDate($observation->observed_on) ?? false,
        ];
    }

    /**
     * Harvest totals of the tree per season of its orchard, newest season first.
     *
     * @return array<int, array{seasonId: int, seasonName: string, weightKg: float, fruitCount: int}>
     */
    public static function seasonYields(Tree $tree): array
    {
        return $tree->orchard->seasons()
            ->orderByDesc('started_on')
            ->get()
            ->map(function (Season $season) use ($tree): array {
                $query = $season->applyDateWindow($tree->harvests()->getQuery(), 'harvested_on');

                return [
                    'seasonId' => $season->id,
                    'seasonName' => $season->name,
                    'weightKg' => (float) $query->sum('weight_kg'),
                    'fruitCount' => (int) $query->sum('fruit_count'),
                ];
            })
            ->all();
    }
}
