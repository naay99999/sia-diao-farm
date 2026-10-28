<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\RecordSerializer;
use App\Models\Finding;
use App\Models\Orchard;
use App\Models\Season;
use App\Models\Tree;
use App\Models\TreeIssue;
use App\Support\IssueSummary;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Date;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_farm_overview')]
#[Description('Call this first in every conversation. Returns the orchards (id, name, current season, active tree count, and every season newest first with its id and dates), issues due for follow-up, all open issues (each with orchard_id), the known findings (id, name, kind) and today\'s date. Use the returned ids in other tools.')]
#[IsReadOnly]
class GetFarmOverview extends Tool
{
    public function handle(Request $request): Response
    {
        $orchards = Orchard::query()
            ->withCount(['trees' => fn ($query) => $query->active()])
            ->with(['seasons' => fn ($query) => $query->orderByDesc('started_on')->orderByDesc('id')])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $openIssues = TreeIssue::query()
            ->open()
            ->whereIn('tree_id', Tree::query()->active()->select('id'))
            ->with(['tree:id,code,orchard_id', 'finding:id,name'])
            ->orderBy('follow_up_on')
            ->orderBy('id')
            ->get();

        $summaries = [];

        foreach ($openIssues as $issue) {
            $summaries[] = [
                ...IssueSummary::forIssue($issue),
                'orchard_id' => $issue->tree->orchard_id,
            ];
        }

        return Response::json([
            'today' => Date::today()->toDateString(),
            'orchards' => $orchards->map(fn (Orchard $orchard): array => [
                'id' => $orchard->id,
                'name' => $orchard->name,
                'current_season' => $orchard->seasons->first(fn (Season $season): bool => $season->isOpen())?->name,
                'active_tree_count' => $orchard->trees_count,
                'seasons' => $orchard->seasons->map(fn (Season $season): array => RecordSerializer::serialize($season))->all(),
            ])->all(),
            'due_issues' => array_values(array_filter($summaries, fn (array $summary): bool => $summary['isDue'])),
            'open_issues' => $summaries,
            'findings' => Finding::query()
                ->orderBy('kind')
                ->orderBy('name')
                ->get()
                ->map(fn (Finding $finding): array => [
                    'id' => $finding->id,
                    'name' => $finding->name,
                    'kind' => $finding->kind->value,
                ])->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
