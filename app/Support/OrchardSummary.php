<?php

namespace App\Support;

use App\Enums\CareType;
use App\Models\Harvest;
use App\Models\Orchard;
use App\Models\Season;

class OrchardSummary
{
    /**
     * Every orchard by name, with its tree layout, open-season totals and latest fertilizer and pesticide care.
     *
     * @return array<int, array{id: int, name: string, treeCount: int, statusCounts: array{due: int, issue: int, normal: int}, layout: array<int, array{row: string|null, trees: array<int, array{id: int, code: string, position: int|null, status: 'due'|'issue'|'normal'}>}>, season: array{id: int, name: string, startedOn: string, yieldKg: float, salesTotal: float, expensesTotal: float}|null, latestFertilizer: array{date: string, product: string|null}|null, latestPesticide: array{date: string, product: string|null}|null}>
     */
    public static function all(): array
    {
        $summaries = [];

        foreach (Orchard::query()->with('currentSeason')->orderBy('name')->orderBy('id')->get() as $orchard) {
            $layout = TreeLayout::forOrchard($orchard);
            $statusCounts = TreeLayout::statusCounts($layout);

            $summaries[] = [
                'id' => $orchard->id,
                'name' => $orchard->name,
                'treeCount' => array_sum($statusCounts),
                'statusCounts' => $statusCounts,
                'layout' => $layout,
                'season' => $orchard->currentSeason === null ? null : self::seasonTotals($orchard, $orchard->currentSeason),
                'latestFertilizer' => self::latestCare($orchard, CareType::Fertilizer),
                'latestPesticide' => self::latestCare($orchard, CareType::Pesticide),
            ];
        }

        return $summaries;
    }

    /**
     * @return array{id: int, name: string, startedOn: string, yieldKg: float, salesTotal: float, expensesTotal: float}
     */
    private static function seasonTotals(Orchard $orchard, Season $season): array
    {
        $harvests = $season->applyDateWindow(
            Harvest::query()
                ->join('trees', 'trees.id', '=', 'harvests.tree_id')
                ->where('trees.orchard_id', $orchard->id),
            'harvests.harvested_on',
        );

        $sales = $season->applyDateWindow($orchard->sales()->getQuery(), 'sold_on');

        return [
            'id' => $season->id,
            'name' => $season->name,
            'startedOn' => $season->started_on->toDateString(),
            'yieldKg' => round((float) $harvests->sum('harvests.weight_kg'), 2),
            'salesTotal' => (float) $sales->sum('amount'),
            'expensesTotal' => (float) $season->expenses()->sum('amount'),
        ];
    }

    /**
     * @return array{date: string, product: string|null}|null
     */
    private static function latestCare(Orchard $orchard, CareType $type): ?array
    {
        $record = $orchard->careRecords()
            ->where('type', $type)
            ->orderByDesc('performed_on')
            ->orderByDesc('id')
            ->first(['performed_on', 'product']);

        return $record === null ? null : [
            'date' => $record->performed_on->toDateString(),
            'product' => $record->product,
        ];
    }
}
