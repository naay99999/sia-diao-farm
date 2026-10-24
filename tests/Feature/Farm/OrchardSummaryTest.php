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

    $due = IssueSummary::dueForFarm();
    $open = IssueSummary::openForFarm();

    expect($due)->toHaveCount(2)
        ->and(array_column($due, 'orchardName'))->toBe(['สวนหน้า', 'สวนหลัง'])
        ->and($due[0]['orchardId'])->toBe($first->id)
        ->and($open)->toHaveCount(3);
});
