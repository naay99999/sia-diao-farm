<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\TreeLookup;
use App\Support\IssueSummary;
use App\Support\TreeStats;
use App\Support\TreeTimeline;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Look up one tree by its tree code (for example A-001). Returns the tree, its open issues, the latest growth stage, the 30 most recent timeline entries (care, harvests, observations; newest first, each with a record id) and harvest totals per season. Pass orchard_id when the farm has more than one orchard. Removed trees are returned too (removed_on is set).')]
#[IsReadOnly]
class GetTree extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'orchard_id' => $schema->integer()->description('Orchard id from get_farm_overview. Optional only when the farm has a single orchard.'),
            'code' => $schema->string()->description('Tree code, for example A-001.')->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'orchard_id' => ['nullable', 'integer'],
            'code' => ['required', 'string'],
        ], [
            'code.required' => 'code is required; pass the tree code, for example A-001.',
        ]);

        $tree = TreeLookup::resolve($validated['orchard_id'] ?? null, [$validated['code']])->firstOrFail();
        $tree->load('orchard');

        return Response::json([
            'tree' => [
                'id' => $tree->id,
                'orchard_id' => $tree->orchard_id,
                'orchard' => $tree->orchard->name,
                'code' => $tree->code,
                'variety' => $tree->variety,
                'planted_on' => $tree->planted_on?->toDateString(),
                'removed_on' => $tree->removed_on?->toDateString(),
                'notes' => $tree->notes,
            ],
            'open_issues' => IssueSummary::forIssues(
                $tree->issues()->open()->with(['tree:id,code', 'finding:id,name'])->orderBy('follow_up_on')->orderBy('id')->get(),
            ),
            'latest_stage' => TreeStats::latestStage($tree),
            'timeline' => TreeTimeline::for($tree, 30),
            'season_yields' => TreeStats::seasonYields($tree),
        ]);
    }
}
