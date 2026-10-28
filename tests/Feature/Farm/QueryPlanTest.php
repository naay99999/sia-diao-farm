<?php

use App\Models\CareRecord;
use App\Models\Harvest;
use App\Models\Season;
use App\Models\Tree;
use App\Models\TreeIssue;
use App\Support\RecordFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The query plan postgres picks for a query, one node per line.
 *
 * The test tables hold a handful of rows, so a sequential scan would always win on cost. Turning it off asks
 * whether an index can serve the query at all, which is what these tests guard.
 *
 * @param  Builder<covariant Model>|string  $query
 * @param  array<int, mixed>  $bindings
 */
function queryPlan(Builder|string $query, array $bindings = []): string
{
    DB::statement('set local enable_seqscan = off');

    $sql = is_string($query) ? $query : $query->toSql();
    $bindings = is_string($query) ? $bindings : $query->getBindings();

    return collect(DB::select('explain '.$sql, $bindings))
        ->pluck('QUERY PLAN')
        ->join("\n");
}

test('the records page sorts by the date index without a separate sort step', function () {
    $query = RecordFilters::apply(CareRecord::query(), ['from' => '2026-01-01', 'to' => '2026-10-31'], 'performed_on', 'orchard_id')
        ->orderByDesc('performed_on')
        ->orderByDesc('id')
        ->limit(50);

    expect(queryPlan($query))->toContain('Index Scan Backward using care_records_performed_on_id_index')
        ->not->toContain('Sort');
});

test('a tree season yield reads the tree and date range from one index', function () {
    $season = Season::factory()->create(['started_on' => '2026-01-01', 'ended_on' => '2026-06-30']);
    $tree = Tree::factory()->create();

    $query = $season->applyDateWindow(Harvest::query()->where('tree_id', $tree->id), 'harvested_on');

    expect(queryPlan($query))->toContain('Index Scan using harvests_tree_id_harvested_on_index')
        ->toMatch('/Index Cond: \(\(tree_id = .+\) AND \(harvested_on >= .+\) AND \(harvested_on < .+\)\)/');
});

test('looking up rows by their foreign key uses an index', function (string $sql) {
    expect(queryPlan($sql, [1]))->toContain('Index');
})->with([
    'tree issues of a tree' => 'select * from tree_issues where tree_id = ?',
    'care records of a tree' => 'select * from care_record_tree where tree_id = ?',
    'care records of an issue' => 'select * from care_record_tree_issue where tree_issue_id = ?',
    'photos of an observation' => 'select * from observation_photos where observation_id = ?',
    'sales of an orchard' => 'select * from sales where orchard_id = ?',
    'expenses of a season' => 'select * from expenses where season_id = ?',
    'seasons of an orchard' => 'select * from seasons where orchard_id = ?',
    'notes of a tree' => 'select * from farm_notes where tree_id = ?',
]);

test('due issues filter the follow-up date on the issue index', function () {
    expect(queryPlan(TreeIssue::query()->due(today())))->toContain('tree_issues_resolved_on_follow_up_on_index');
});
