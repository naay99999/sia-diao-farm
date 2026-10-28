<?php

use App\Models\CareRecord;
use App\Models\Harvest;
use App\Models\Observation;
use App\Models\Orchard;
use App\Models\Season;
use App\Models\Tree;
use App\Support\TreeStats;
use App\Support\TreeTimeline;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => Date::setTestNow('2026-10-24'));

test('season yields total each season on its own, boundary days included', function () {
    $orchard = Orchard::factory()->create();
    $old = Season::factory()->for($orchard)->create(['started_on' => '2025-01-01', 'ended_on' => '2025-06-30']);
    $empty = Season::factory()->for($orchard)->create(['started_on' => '2025-07-01', 'ended_on' => '2025-12-31']);
    $open = Season::factory()->for($orchard)->create(['started_on' => '2026-01-01', 'ended_on' => null]);
    $tree = Tree::factory()->for($orchard)->create();
    Harvest::factory()->for($tree)->create(['harvested_on' => '2025-01-01', 'weight_kg' => 1, 'fruit_count' => 2]);
    Harvest::factory()->for($tree)->create(['harvested_on' => '2025-06-30', 'weight_kg' => 2.5, 'fruit_count' => null]);
    Harvest::factory()->for($tree)->create(['harvested_on' => '2026-10-24', 'weight_kg' => 4, 'fruit_count' => 5]);
    Harvest::factory()->for($tree)->create(['harvested_on' => '2026-10-25', 'weight_kg' => 100]);
    Harvest::factory()->for(Tree::factory()->for($orchard)->create())->create(['harvested_on' => '2026-02-01', 'weight_kg' => 50]);
    Harvest::factory()->for($tree)->create(['harvested_on' => '2026-02-01', 'weight_kg' => 70])->delete();

    expect(TreeStats::seasonYields($tree->load('orchard')))->toBe([
        ['seasonId' => $open->id, 'seasonName' => $open->name, 'weightKg' => 4.0, 'fruitCount' => 5],
        ['seasonId' => $empty->id, 'seasonName' => $empty->name, 'weightKg' => 0.0, 'fruitCount' => 0],
        ['seasonId' => $old->id, 'seasonName' => $old->name, 'weightKg' => 3.5, 'fruitCount' => 2],
    ]);
});

test('season yields run the same number of queries however many seasons there are', function () {
    $orchard = Orchard::factory()->create();
    $tree = Tree::factory()->for($orchard)->create();

    $count = function (int $seasons) use ($orchard, $tree): int {
        foreach (range(1, $seasons) as $i) {
            Season::factory()->for($orchard)->create(['started_on' => "20{$i}0-01-01", 'ended_on' => "20{$i}0-12-31"]);
        }
        $tree->load('orchard');

        DB::enableQueryLog();
        DB::flushQueryLog();
        TreeStats::seasonYields($tree);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($count(5))->toBe($count(1));
});

test('the timeline keeps the newest entries across kinds when each kind has more than the limit', function () {
    $orchard = Orchard::factory()->create();
    $tree = Tree::factory()->for($orchard)->create();

    foreach (range(1, 4) as $day) {
        Harvest::factory()->for($tree)->create(['harvested_on' => "2026-01-0{$day}"]);
        Observation::factory()->for($tree)->create(['observed_on' => "2026-02-0{$day}"]);
        CareRecord::factory()->for($orchard)->create(['performed_on' => "2025-12-0{$day}"])->trees()->attach($tree);
    }

    $timeline = TreeTimeline::for($tree, 3);

    expect(array_column($timeline, 'date'))->toBe(['2026-02-04', '2026-02-03', '2026-02-02'])
        ->and(array_column($timeline, 'kind'))->toBe(['observation', 'observation', 'observation']);

    expect(array_column(TreeTimeline::for($tree, 6), 'date'))
        ->toBe(['2026-02-04', '2026-02-03', '2026-02-02', '2026-02-01', '2026-01-04', '2026-01-03']);
});
