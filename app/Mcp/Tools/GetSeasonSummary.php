<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\RecordSerializer;
use App\Models\Season;
use App\Support\SeasonSummary;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_season_summary')]
#[Description('Totals of one season, computed by the server: harvest weight and fruit count, sales (amount, weight, average price per kg), expenses, profit (sales minus expenses), the number of care records, and the yield of every harvested tree (heaviest first) with kg per harvested tree. Harvests, sales and care count when their date falls inside the season (an open season runs to today); expenses count only when they are linked to the season, so general expenses are left out. Use this instead of adding up list_records results.')]
#[IsReadOnly]
class GetSeasonSummary extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'season_id' => $schema->integer()->description('Season id from get_farm_overview.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate(['season_id' => ['required', 'integer']]);

        $season = Season::query()->with('orchard')->find((int) $validated['season_id']) ?? throw ValidationException::withMessages([
            'season_id' => "No season with id {$validated['season_id']} exists; use a season id from get_farm_overview.",
        ]);

        $treeYields = SeasonSummary::treeYields($season);
        $totals = SeasonSummary::totals($season);
        $yieldKg = round(array_sum(array_column($treeYields, 'weightKg')), 2);

        return Response::json([
            'season' => [...RecordSerializer::serialize($season), 'orchard' => $season->orchard->name],
            'harvest' => [
                'total_weight_kg' => $yieldKg,
                'total_fruit_count' => array_sum(array_column($treeYields, 'fruitCount')),
                'harvested_tree_count' => count($treeYields),
                'avg_kg_per_harvested_tree' => $treeYields === [] ? null : round($yieldKg / count($treeYields), 2),
            ],
            'sales' => [
                'total_amount' => $totals['salesTotal'],
                'total_weight_kg' => $totals['salesWeightKg'],
                'avg_price_per_kg' => $totals['salesWeightKg'] > 0 ? round($totals['salesTotal'] / $totals['salesWeightKg'], 2) : null,
            ],
            'expenses_total' => $totals['expensesTotal'],
            'profit' => round($totals['salesTotal'] - $totals['expensesTotal'], 2),
            'care_record_count' => $totals['careRecordCount'],
            'tree_yields' => array_map(fn (array $row): array => [
                'tree_code' => $row['code'],
                'weight_kg' => $row['weightKg'],
                'fruit_count' => $row['fruitCount'],
            ], $treeYields),
        ]);
    }
}
