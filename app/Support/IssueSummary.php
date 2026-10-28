<?php

namespace App\Support;

use App\Models\Orchard;
use App\Models\Tree;
use App\Models\TreeIssue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;

class IssueSummary
{
    /**
     * Expects `tree` and `finding` to be loaded; use {@see self::forIssues()} for collections.
     *
     * @return array{id: int, treeId: int, treeCode: string, finding: string, openedOn: string, followUpOn: string, isDue: bool}
     */
    public static function forIssue(TreeIssue $issue): array
    {
        return [
            'id' => $issue->id,
            'treeId' => $issue->tree_id,
            'treeCode' => $issue->tree->code,
            'finding' => $issue->finding->name,
            'openedOn' => $issue->opened_on->toDateString(),
            'followUpOn' => $issue->follow_up_on->toDateString(),
            'isDue' => $issue->follow_up_on->toDateString() <= Date::today()->toDateString(),
        ];
    }

    /**
     * @param  iterable<TreeIssue>  $issues
     * @return array<int, array{id: int, treeId: int, treeCode: string, finding: string, openedOn: string, followUpOn: string, isDue: bool}>
     */
    public static function forIssues(iterable $issues): array
    {
        $summaries = [];

        foreach ($issues as $issue) {
            $summaries[] = self::forIssue($issue);
        }

        return $summaries;
    }

    /**
     * Open issues on the orchard's standing trees, soonest follow-up first.
     *
     * @return array<int, array{id: int, treeId: int, treeCode: string, finding: string, openedOn: string, followUpOn: string, isDue: bool}>
     */
    public static function openForOrchard(Orchard $orchard): array
    {
        return self::forIssues(
            self::orchardQuery($orchard)->open()->get(),
        );
    }

    /**
     * Open issues on standing trees across every orchard, soonest follow-up first.
     *
     * @return array<int, array{id: int, treeId: int, treeCode: string, finding: string, openedOn: string, followUpOn: string, isDue: bool, orchardId: int, orchardName: string}>
     */
    public static function openForFarm(): array
    {
        return self::withOrchard(self::farmQuery()->open()->get());
    }

    /**
     * The open issue summaries already due, keeping their order.
     *
     * @template TSummary of array{isDue: bool}
     *
     * @param  array<int, TSummary>  $openIssues
     * @return array<int, TSummary>
     */
    public static function due(array $openIssues): array
    {
        return array_values(Arr::where($openIssues, fn (array $issue): bool => $issue['isDue']));
    }

    /**
     * @param  iterable<TreeIssue>  $issues
     * @return array<int, array{id: int, treeId: int, treeCode: string, finding: string, openedOn: string, followUpOn: string, isDue: bool, orchardId: int, orchardName: string}>
     */
    private static function withOrchard(iterable $issues): array
    {
        $summaries = [];

        foreach ($issues as $issue) {
            $summaries[] = self::forIssue($issue) + [
                'orchardId' => $issue->tree->orchard->id,
                'orchardName' => $issue->tree->orchard->name,
            ];
        }

        return $summaries;
    }

    /**
     * @return Builder<TreeIssue>
     */
    private static function farmQuery(): Builder
    {
        return TreeIssue::query()
            ->whereIn('tree_id', Tree::query()->active()->select('id'))
            ->with(['tree:id,code,orchard_id', 'tree.orchard:id,name', 'finding:id,name'])
            ->orderBy('follow_up_on')
            ->orderBy('id');
    }

    /**
     * @return Builder<TreeIssue>
     */
    private static function orchardQuery(Orchard $orchard): Builder
    {
        return TreeIssue::query()
            ->whereIn('tree_id', Tree::query()->active()->where('orchard_id', $orchard->id)->select('id'))
            ->with(['tree:id,code', 'finding:id,name'])
            ->orderBy('follow_up_on')
            ->orderBy('id');
    }
}
