<?php

namespace App\Support;

use App\Enums\CareType;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\Harvest;
use App\Models\Orchard;
use App\Models\Sale;
use App\Models\Season;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class OrchardSummary
{
    /**
     * Every orchard by name, with its tree layout, open-season totals and latest fertilizer and pesticide care,
     * in a fixed number of queries however many orchards there are.
     *
     * @return array<int, array{id: int, name: string, treeCount: int, statusCounts: array{due: int, issue: int, normal: int}, layout: array<int, array{row: string|null, trees: array<int, array{id: int, code: string, position: int|null, status: 'due'|'issue'|'normal'}>}>, season: array{id: int, name: string, startedOn: string, yieldKg: float, salesTotal: float, expensesTotal: float}|null, latestFertilizer: array{date: string, product: string|null}|null, latestPesticide: array{date: string, product: string|null}|null}>
     */
    public static function all(): array
    {
        $orchards = Orchard::query()->with('currentSeason')->orderBy('name')->orderBy('id')->get();

        /** @var Collection<int, Season> $seasons */
        $seasons = $orchards->pluck('currentSeason')->filter()->values();
        $seasonIds = $seasons->pluck('id')->all();

        $layouts = TreeLayout::forOrchards($orchards);
        $yields = self::openSeasonYields($seasonIds);
        $sales = self::openSeasonSales($seasonIds);
        $expenses = Expense::query()
            ->whereIn('season_id', $seasonIds)
            ->groupBy('season_id')
            ->selectRaw('season_id, sum(amount) as amount')
            ->pluck('amount', 'season_id');
        $latestCare = self::latestCare([CareType::Fertilizer, CareType::Pesticide]);

        $summaries = [];

        foreach ($orchards as $orchard) {
            $layout = $layouts[$orchard->id];
            $statusCounts = TreeLayout::statusCounts($layout);
            $season = $orchard->currentSeason;

            $summaries[] = [
                'id' => $orchard->id,
                'name' => $orchard->name,
                'treeCount' => array_sum($statusCounts),
                'statusCounts' => $statusCounts,
                'layout' => $layout,
                'season' => $season === null ? null : [
                    'id' => $season->id,
                    'name' => $season->name,
                    'startedOn' => $season->started_on->toDateString(),
                    'yieldKg' => round($yields[$orchard->id] ?? 0.0, 2),
                    'salesTotal' => (float) ($sales[$orchard->id] ?? 0),
                    'expensesTotal' => (float) ($expenses[$season->id] ?? 0),
                ],
                'latestFertilizer' => $latestCare[$orchard->id][CareType::Fertilizer->value] ?? null,
                'latestPesticide' => $latestCare[$orchard->id][CareType::Pesticide->value] ?? null,
            ];
        }

        return $summaries;
    }

    /**
     * Harvest kg per orchard id inside each given open season, up to today.
     *
     * @param  array<int, int>  $seasonIds
     * @return Collection<int, float>
     */
    public static function openSeasonYields(array $seasonIds): Collection
    {
        return Harvest::query()
            ->join('trees', 'trees.id', '=', 'harvests.tree_id')
            ->join('seasons', fn (JoinClause $join) => self::joinOpenSeason($join, 'trees.orchard_id', 'harvests.harvested_on', $seasonIds))
            ->where('harvests.harvested_on', '<', Date::tomorrow()->toDateString())
            ->groupBy('trees.orchard_id')
            ->selectRaw('trees.orchard_id, sum(harvests.weight_kg) as weight_kg')
            ->pluck('weight_kg', 'orchard_id')
            ->map(fn ($weight): float => (float) $weight);
    }

    /**
     * Sale amount per orchard id inside each given open season, up to today.
     *
     * @param  array<int, int>  $seasonIds
     * @return Collection<int, float>
     */
    private static function openSeasonSales(array $seasonIds): Collection
    {
        return Sale::query()
            ->join('seasons', fn (JoinClause $join) => self::joinOpenSeason($join, 'sales.orchard_id', 'sales.sold_on', $seasonIds))
            ->where('sales.sold_on', '<', Date::tomorrow()->toDateString())
            ->groupBy('sales.orchard_id')
            ->selectRaw('sales.orchard_id, sum(sales.amount) as amount')
            ->pluck('amount', 'orchard_id')
            ->map(fn ($amount): float => (float) $amount);
    }

    /**
     * @param  array<int, int>  $seasonIds
     */
    private static function joinOpenSeason(JoinClause $join, string $orchardColumn, string $dateColumn, array $seasonIds): void
    {
        $join->on('seasons.orchard_id', '=', $orchardColumn)
            ->whereIn('seasons.id', $seasonIds)
            ->whereColumn($dateColumn, '>=', 'seasons.started_on');
    }

    /**
     * The newest care record of each given type per orchard, keyed by orchard id then type.
     *
     * @param  array<int, CareType>  $types
     * @return array<int, array<string, array{date: string, product: string|null}>>
     */
    private static function latestCare(array $types): array
    {
        $ranked = CareRecord::query()
            ->whereIn('type', array_map(fn (CareType $type): string => $type->value, $types))
            ->select(['orchard_id', 'type', 'performed_on', 'product'])
            ->selectRaw('row_number() over (partition by orchard_id, type order by performed_on desc, id desc) as position');

        $latest = [];

        foreach (DB::query()->fromSub($ranked, 'ranked')->where('position', 1)->get() as $row) {
            $latest[(int) $row->orchard_id][$row->type] = [
                'date' => Date::parse($row->performed_on)->toDateString(),
                'product' => $row->product,
            ];
        }

        return $latest;
    }
}
