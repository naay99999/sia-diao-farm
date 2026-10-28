<?php

use App\Enums\CareType;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\Harvest;
use App\Models\Orchard;
use App\Models\Sale;
use App\Models\Season;
use App\Models\Tree;
use App\Models\TreeIssue;
use App\Support\IssueSummary;
use App\Support\OrchardSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

beforeEach(fn () => Date::setTestNow('2026-10-24'));

test('summary carries season totals for the open season', function () {
    $orchard = Orchard::factory()->create();
    $season = Season::factory()->for($orchard)->create(['started_on' => '2026-01-01', 'ended_on' => null]);
    $tree = Tree::factory()->for($orchard)->create();
    Harvest::factory()->for($tree)->create(['harvested_on' => '2026-05-01', 'weight_kg' => 12.5]);
    Harvest::factory()->for($tree)->create(['harvested_on' => '2025-12-31', 'weight_kg' => 3]);
    Sale::factory()->for($orchard)->create(['sold_on' => '2026-06-01', 'amount' => 4000]);
    Sale::factory()->for($orchard)->create(['sold_on' => '2025-06-01', 'amount' => 999]);
    Expense::factory()->create(['season_id' => $season->id, 'amount' => 900]);

    $summary = OrchardSummary::all()[0];

    expect($summary['id'])->toBe($orchard->id)
        ->and($summary['treeCount'])->toBe(1)
        ->and($summary['season'])->toBe([
            'id' => $season->id,
            'name' => $season->name,
            'startedOn' => '2026-01-01',
            'yieldKg' => 12.5,
            'salesTotal' => 4000.0,
            'expensesTotal' => 900.0,
        ]);
});

test('orchard without an open season has a null season', function () {
    $orchard = Orchard::factory()->create();
    Season::factory()->closed()->for($orchard)->create();

    expect(OrchardSummary::all()[0]['season'])->toBeNull();
});

test('latest fertilizer and pesticide are the newest of each type', function () {
    $orchard = Orchard::factory()->create();
    CareRecord::factory()->for($orchard)->create(['type' => CareType::Fertilizer, 'performed_on' => '2026-09-01', 'product' => 'ปุ๋ยคอก']);
    CareRecord::factory()->for($orchard)->create(['type' => CareType::Fertilizer, 'performed_on' => '2026-10-01', 'product' => '15-15-15']);
    CareRecord::factory()->for($orchard)->create(['type' => CareType::Pesticide, 'performed_on' => '2026-10-10', 'product' => 'อะบาเม็กติน']);
    CareRecord::factory()->for($orchard)->create(['type' => CareType::Pesticide, 'performed_on' => '2026-10-20', 'product' => 'ลบแล้ว'])->delete();

    $summary = OrchardSummary::all()[0];

    expect($summary['latestFertilizer'])->toBe(['date' => '2026-10-01', 'product' => '15-15-15'])
        ->and($summary['latestPesticide'])->toBe(['date' => '2026-10-10', 'product' => 'อะบาเม็กติน']);
});

test('each orchard gets only its own season totals and care', function () {
    $front = Orchard::factory()->create(['name' => 'ก']);
    $back = Orchard::factory()->create(['name' => 'ข']);
    $frontSeason = Season::factory()->for($front)->create(['started_on' => '2026-01-01', 'ended_on' => null]);
    $backSeason = Season::factory()->for($back)->create(['started_on' => '2026-06-01', 'ended_on' => null]);
    Season::factory()->for($back)->create(['started_on' => '2025-01-01', 'ended_on' => '2025-12-31']);
    Harvest::factory()->for(Tree::factory()->for($front)->create())->create(['harvested_on' => '2026-03-01', 'weight_kg' => 10]);
    Harvest::factory()->for(Tree::factory()->for($back)->create())->create(['harvested_on' => '2026-03-01', 'weight_kg' => 7]);
    Harvest::factory()->for(Tree::factory()->for($back)->create())->create(['harvested_on' => '2026-07-01', 'weight_kg' => 4.25]);
    Harvest::factory()->for(Tree::factory()->for($back)->create())->create(['harvested_on' => '2026-10-25', 'weight_kg' => 99]);
    Sale::factory()->for($front)->create(['sold_on' => '2026-02-01', 'amount' => 100]);
    Sale::factory()->for($back)->create(['sold_on' => '2026-02-01', 'amount' => 50]);
    Sale::factory()->for($back)->create(['sold_on' => '2026-10-24', 'amount' => 300]);
    Expense::factory()->create(['season_id' => $frontSeason->id, 'amount' => 20]);
    Expense::factory()->create(['season_id' => $backSeason->id, 'amount' => 30]);
    CareRecord::factory()->for($front)->create(['type' => CareType::Fertilizer, 'performed_on' => '2026-10-01', 'product' => 'หน้า']);
    CareRecord::factory()->for($back)->create(['type' => CareType::Fertilizer, 'performed_on' => '2026-09-01', 'product' => 'หลัง']);

    [$frontSummary, $backSummary] = OrchardSummary::all();

    expect($frontSummary['season'])->toMatchArray(['yieldKg' => 10.0, 'salesTotal' => 100.0, 'expensesTotal' => 20.0])
        ->and($backSummary['season'])->toMatchArray(['id' => $backSeason->id, 'yieldKg' => 4.25, 'salesTotal' => 300.0, 'expensesTotal' => 30.0])
        ->and($frontSummary['latestFertilizer'])->toBe(['date' => '2026-10-01', 'product' => 'หน้า'])
        ->and($backSummary['latestFertilizer'])->toBe(['date' => '2026-09-01', 'product' => 'หลัง'])
        ->and($backSummary['latestPesticide'])->toBeNull();
});

test('orchards are ordered by name and report null care when none exists', function () {
    Orchard::factory()->create(['name' => 'ข']);
    Orchard::factory()->create(['name' => 'ก']);

    $summaries = OrchardSummary::all();

    expect(array_column($summaries, 'name'))->toBe(['ก', 'ข'])
        ->and($summaries[0]['latestFertilizer'])->toBeNull()
        ->and($summaries[0]['latestPesticide'])->toBeNull();
});

test('farm-wide due issues span orchards and name the orchard', function () {
    $first = Orchard::factory()->create(['name' => 'สวนหน้า']);
    $second = Orchard::factory()->create(['name' => 'สวนหลัง']);
    TreeIssue::factory()->for(Tree::factory()->for($first)->create())->create(['follow_up_on' => '2026-10-20', 'resolved_on' => null]);
    TreeIssue::factory()->for(Tree::factory()->for($second)->create())->create(['follow_up_on' => '2026-10-22', 'resolved_on' => null]);
    TreeIssue::factory()->for(Tree::factory()->for($second)->create())->create(['follow_up_on' => '2026-11-30', 'resolved_on' => null]);

    $open = IssueSummary::openForFarm();
    $due = IssueSummary::due($open);

    expect($due)->toHaveCount(2)
        ->and(array_column($due, 'orchardName'))->toBe(['สวนหน้า', 'สวนหลัง'])
        ->and($due[0]['orchardId'])->toBe($first->id)
        ->and($open)->toHaveCount(3);
});
