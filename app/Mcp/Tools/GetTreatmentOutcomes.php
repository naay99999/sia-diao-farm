<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Query\Builder;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use stdClass;

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

        $outcomes = DB::query()
            ->fromSub($this->treatedIssues($finding), 'treated')
            ->groupBy('finding', 'product')
            ->select(['finding', 'product'])
            ->selectRaw('count(*) as issues_treated, count(resolved_on) as resolved, avg(resolved_on - opened_on) as avg_days_to_resolve')
            ->get()
            ->map(fn (stdClass $row): array => [
                'finding' => $row->finding,
                'product' => $row->product,
                'issues_treated' => (int) $row->issues_treated,
                'resolved' => (int) $row->resolved,
                'avg_days_to_resolve' => $row->avg_days_to_resolve === null ? null : round((float) $row->avg_days_to_resolve, 1),
            ])
            ->sortBy([['finding', 'asc'], ['issues_treated', 'desc'], ['product', 'asc']])
            ->values()
            ->all();

        return Response::json(['outcomes' => $outcomes]);
    }

    /**
     * One row per finding, product and live issue it was used on, so an issue treated twice with the same
     * product counts once. Deleted issues and care records are left out.
     */
    private function treatedIssues(string $finding): Builder
    {
        return DB::table('tree_issues')
            ->join('findings', 'findings.id', '=', 'tree_issues.finding_id')
            ->join('care_record_tree_issue', 'care_record_tree_issue.tree_issue_id', '=', 'tree_issues.id')
            ->join('care_records', 'care_records.id', '=', 'care_record_tree_issue.care_record_id')
            ->whereNull('tree_issues.deleted_at')
            ->whereNull('care_records.deleted_at')
            ->when($finding !== '', fn (Builder $query) => $query->where('findings.name', $finding))
            ->whereRaw("trim(care_records.product) <> ''")
            ->distinct()
            ->select(['findings.name as finding', 'tree_issues.id', 'tree_issues.opened_on', 'tree_issues.resolved_on'])
            ->selectRaw('trim(care_records.product) as product');
    }
}
