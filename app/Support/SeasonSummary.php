<?php

namespace App\Support;

use App\Models\Harvest;
use App\Models\Sale;
use App\Models\Season;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Yield, sales and expense totals of one season, shared by the season page and the MCP tools.
 */
class SeasonSummary
{
    /**
     * Harvest totals per tree in the season, heaviest first.
     *
     * @return list<array{treeId: int, code: string, weightKg: float, fruitCount: int}>
     */
    public static function treeYields(Season $season): array
    {
        return array_values($season->applyDateWindow(
            Harvest::query()
                ->join('trees', 'trees.id', '=', 'harvests.tree_id')
                ->where('trees.orchard_id', $season->orchard_id),
            'harvests.harvested_on',
        )
            ->groupBy('trees.id', 'trees.code')
            ->orderByRaw('sum(harvests.weight_kg) desc')
            ->orderBy('trees.code')
            ->toBase()
            ->get([
                'trees.id as tree_id',
                'trees.code',
                DB::raw('sum(harvests.weight_kg) as weight_kg'),
                DB::raw('coalesce(sum(harvests.fruit_count), 0) as fruit_count'),
            ])
            ->map(fn (stdClass $row): array => [
                'treeId' => (int) $row->tree_id,
                'code' => (string) $row->code,
                'weightKg' => (float) $row->weight_kg,
                'fruitCount' => (int) $row->fruit_count,
            ])
            ->all());
    }

    /**
     * The orchard's sales dated inside the season.
     *
     * @return Builder<Sale>
     */
    public static function sales(Season $season): Builder
    {
        return $season->applyDateWindow($season->orchard->sales()->getQuery(), 'sold_on');
    }

    /**
     * @return array{salesTotal: float, salesWeightKg: float, expensesTotal: float, careRecordCount: int}
     */
    public static function totals(Season $season): array
    {
        $sales = self::sales($season);

        return [
            'salesTotal' => (float) (clone $sales)->sum('amount'),
            'salesWeightKg' => (float) (clone $sales)->sum('weight_kg'),
            'expensesTotal' => (float) $season->expenses()->sum('amount'),
            'careRecordCount' => $season->applyDateWindow($season->orchard->careRecords()->getQuery(), 'performed_on')->count(),
        ];
    }
}
