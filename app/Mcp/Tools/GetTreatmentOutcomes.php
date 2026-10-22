<?php

namespace App\Mcp\Tools;

use App\Models\CareRecord;
use App\Models\TreeIssue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_treatment_outcomes')]
#[Description('The farm\'s own track record of treatments. For each combination of finding (problem name) and care product linked to an issue, returns issues_treated, resolved and avg_days_to_resolve (days from opening to resolving, resolved issues only; null when none resolved). Use it before recommending a treatment, and say how small the sample is. Pass finding (the exact name from get_farm_overview) to limit it to one problem.')]
#[IsReadOnly]
class GetTreatmentOutcomes extends Tool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'finding' => $schema->string()->description('Exact finding name, for example ไรแดง. Omit to include every finding.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate(['finding' => ['nullable', 'string']]);
        $finding = trim((string) ($validated['finding'] ?? ''));

        $issues = TreeIssue::query()
            ->whereHas('careRecords')
            ->when($finding !== '', fn ($query) => $query->whereRelation('finding', 'name', $finding))
            ->with(['finding:id,name', 'careRecords:id,product'])
            ->get();

        /** @var Collection<string, array{finding: string, product: string, issues: Collection<int, TreeIssue>}> $groups */
        $groups = collect();

        foreach ($issues as $issue) {
            $products = $issue->careRecords
                ->map(fn (CareRecord $record): string => trim((string) $record->product))
                ->filter()
                ->unique();

            foreach ($products as $product) {
                $key = $issue->finding->name."\0".$product;
                $group = $groups->get($key, ['finding' => $issue->finding->name, 'product' => $product, 'issues' => collect()]);
                $group['issues']->push($issue);
                $groups->put($key, $group);
            }
        }

        $outcomes = $groups
            ->map(function (array $group): array {
                $resolved = $group['issues']->reject(fn (TreeIssue $issue): bool => $issue->isOpen());

                return [
                    'finding' => $group['finding'],
                    'product' => $group['product'],
                    'issues_treated' => $group['issues']->count(),
                    'resolved' => $resolved->count(),
                    'avg_days_to_resolve' => $resolved->isEmpty() ? null : round(
                        $resolved->avg(fn (TreeIssue $issue): int => (int) $issue->opened_on->diffInDays($issue->resolved_on)),
                        1,
                    ),
                ];
            })
            ->sortBy([['finding', 'asc'], ['issues_treated', 'desc'], ['product', 'asc']])
            ->values()
            ->all();

        return Response::json(['outcomes' => $outcomes]);
    }
}
