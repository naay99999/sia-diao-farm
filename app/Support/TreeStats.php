<?php

namespace App\Support;

use App\Enums\FindingKind;
use App\Models\Harvest;
use App\Models\Season;
use App\Models\Tree;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

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
     * Harvest totals of the tree per season of its orchard, newest season first, in two queries however many
     * seasons there are.
     *
     * @return array<int, array{seasonId: int, seasonName: string, weightKg: float, fruitCount: int}>
     */
    public static function seasonYields(Tree $tree): array
    {
        $totals = Harvest::query()
            ->where('harvests.tree_id', $tree->id)
            ->join('seasons', function (JoinClause $join) use ($tree): void {
                $join->where('seasons.orchard_id', $tree->orchard_id)
                    ->whereColumn('harvests.harvested_on', '>=', 'seasons.started_on')
                    ->where(fn (JoinClause $end) => $end
                        ->whereColumn('harvests.harvested_on', '<=', 'seasons.ended_on')
                        ->orWhere(fn (JoinClause $open) => $open
                            ->whereNull('seasons.ended_on')
                            ->where('harvests.harvested_on', '<', Date::tomorrow()->toDateString())));
            })
            ->groupBy('seasons.id')
            ->toBase()
            ->get([
                'seasons.id as season_id',
                DB::raw('sum(harvests.weight_kg) as weight_kg'),
                DB::raw('coalesce(sum(harvests.fruit_count), 0) as fruit_count'),
            ])
            ->keyBy('season_id');

        return $tree->orchard->seasons()
            ->orderByDesc('started_on')
            ->get()
            ->map(fn (Season $season): array => [
                'seasonId' => $season->id,
                'seasonName' => $season->name,
                'weightKg' => (float) ($totals[$season->id]->weight_kg ?? 0),
                'fruitCount' => (int) ($totals[$season->id]->fruit_count ?? 0),
            ])
            ->all();
    }
}
