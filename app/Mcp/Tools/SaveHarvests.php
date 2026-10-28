<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\RecordSerializer;
use App\Mcp\Support\TreeLookup;
use App\Models\Harvest;
use App\Models\Tree;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('save_harvests')]
#[Description('Only call after the user confirmed a summary in chat. Records the harvest of several trees picked on the same day, one row per tree. Nothing is saved if any row is invalid or names a removed tree. To correct one harvest later, use save_harvest with its id. Pass orchard_id when the farm has more than one orchard. Returns the saved harvests with their ids.')]
class SaveHarvests extends FarmWriteTool
{
    public const MAX_ROWS = 500;

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'orchard_id' => $schema->integer()->description('Orchard id from get_farm_overview. Optional only when the farm has a single orchard.'),
            'harvested_on' => $schema->string()->description('Harvest date, YYYY-MM-DD.')->required(),
            'rows' => $schema->array()
                ->items($schema->object([
                    'tree_code' => $schema->string()->description('Tree code, for example A-001.')->required(),
                    'weight_kg' => $schema->number()->min(0)->description('Total weight in kilograms, greater than 0.')->required(),
                    'fruit_count' => $schema->integer()->min(1)->description('Number of fruits, if counted.'),
                ]))
                ->min(1)
                ->max(self::MAX_ROWS)
                ->description('One row per tree, each tree at most once.')
                ->required(),
        ];
    }

    protected function save(Request $request): Response
    {
        $validated = $this->validated($request, [
            'orchard_id' => ['nullable', 'integer'],
            'harvested_on' => ['required', 'date'],
            'rows' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS],
            'rows.*.tree_code' => ['required', 'string', 'distinct:ignore_case'],
            'rows.*.weight_kg' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'rows.*.fruit_count' => ['nullable', 'integer', 'min:1'],
        ], [
            'rows.required' => 'rows is required; pass one row per tree.',
            'rows.*.tree_code.required' => 'Every row needs a tree_code.',
            'rows.*.tree_code.distinct' => 'Each tree may appear only once; :input is repeated.',
            'rows.*.weight_kg.required' => 'Every row needs weight_kg in kilograms.',
            'rows.*.weight_kg.gt' => 'weight_kg must be greater than 0.',
            'rows.*.fruit_count.min' => 'fruit_count must be at least 1; omit it if the fruits were not counted.',
            ...$this->dateMessages('harvested_on'),
        ]);

        $rows = $validated['rows'];
        $trees = TreeLookup::resolve($validated['orchard_id'] ?? null, array_column($rows, 'tree_code'));
        $removed = $trees->filter(fn (Tree $tree): bool => $tree->isRemoved())->pluck('code');

        if ($removed->isNotEmpty()) {
            throw ValidationException::withMessages([
                'rows' => "Tree(s) {$removed->join(', ')} have been removed, so harvests cannot be recorded for them. Nothing was saved.",
            ]);
        }

        $harvests = Harvest::query()->getConnection()->transaction(fn (): array => array_map(
            fn (array $row, Tree $tree): Harvest => $tree->harvests()->create([
                'harvested_on' => $validated['harvested_on'],
                'weight_kg' => $row['weight_kg'],
                'fruit_count' => $row['fruit_count'] ?? null,
            ]),
            $rows,
            $trees->all(),
        ));

        return Response::json([
            'saved' => count($harvests),
            'total_weight_kg' => round(array_sum(array_map(fn (Harvest $harvest): float => (float) $harvest->weight_kg, $harvests)), 2),
            'harvests' => array_map(fn (Harvest $harvest): array => RecordSerializer::serialize($harvest), $harvests),
        ]);
    }
}
