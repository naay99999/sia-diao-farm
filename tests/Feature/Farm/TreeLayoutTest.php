<?php

use App\Models\Orchard;
use App\Models\Tree;
use App\Models\TreeIssue;
use App\Support\TreeLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('codes parse into row and position', function (string $code, ?array $expected) {
    expect(TreeLayout::parseCode($code))->toBe($expected);
})->with([
    ['A-05', ['row' => 'A', 'position' => 5]],
    ['a5', ['row' => 'A', 'position' => 5]],
    ['AB-12', ['row' => 'AB', 'position' => 12]],
    ['โคนหลัง', null],
    ['12', null],
    ['A-', null],
]);

test('layout groups active trees by row in order and marks status', function () {
    Date::setTestNow('2026-10-24');
    $orchard = Orchard::factory()->create();
    foreach (['B-02', 'A-10', 'A-02', 'AA-01', 'โคนหลัง'] as $code) {
        Tree::factory()->for($orchard)->create(['code' => $code]);
    }
    Tree::factory()->for($orchard)->removed()->create(['code' => 'A-03']);
    $a2 = Tree::where('code', 'A-02')->first();
    $a10 = Tree::where('code', 'A-10')->first();
    TreeIssue::factory()->for($a2)->create(['follow_up_on' => '2026-10-23', 'resolved_on' => null]);
    TreeIssue::factory()->for($a10)->create(['follow_up_on' => '2026-10-31', 'resolved_on' => null]);

    $layout = TreeLayout::forOrchard($orchard);

    expect(array_column($layout, 'row'))->toBe(['A', 'B', 'AA', null])
        ->and(array_column($layout[0]['trees'], 'code'))->toBe(['A-02', 'A-10'])
        ->and(array_column($layout[0]['trees'], 'status'))->toBe(['due', 'issue'])
        ->and($layout[3]['trees'][0]['position'])->toBeNull()
        ->and(TreeLayout::statusCounts($layout))->toBe(['due' => 1, 'issue' => 1, 'normal' => 3]);
});

test('unparseable codes go to the other row', function () {
    $orchard = Orchard::factory()->create();
    foreach (['โคนหลัง', '12'] as $code) {
        Tree::factory()->for($orchard)->create(['code' => $code]);
    }

    $layout = TreeLayout::forOrchard($orchard);

    expect($layout)->toHaveCount(1)
        ->and($layout[0]['row'])->toBeNull()
        ->and(array_column($layout[0]['trees'], 'code'))->toBe(['12', 'โคนหลัง']);
});

test('layout runs a constant number of queries', function () {
    $count = function (int $trees): int {
        $orchard = Orchard::factory()->create();
        foreach (range(1, $trees) as $i) {
            $tree = Tree::factory()->for($orchard)->create(['code' => sprintf('A-%02d', $i)]);
            TreeIssue::factory()->for($tree)->create(['resolved_on' => null]);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        TreeLayout::forOrchard($orchard);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($count(30))->toBe($count(3));
});
