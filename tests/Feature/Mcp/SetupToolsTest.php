<?php

use App\Enums\ExpenseCategory;
use App\Mcp\Servers\FarmServer;
use App\Mcp\Tools\AddTrees;
use App\Mcp\Tools\GetSeasonSummary;
use App\Mcp\Tools\SaveHarvests;
use App\Mcp\Tools\SaveOrchard;
use App\Mcp\Tools\SaveSeason;
use App\Mcp\Tools\SetTreeRemoved;
use App\Mcp\Tools\UpdateTree;
use App\Models\Expense;
use App\Models\Harvest;
use App\Models\Orchard;
use App\Models\Sale;
use App\Models\Season;
use App\Models\Tree;
use App\Models\User;
use Illuminate\Support\Facades\Date;

beforeEach(function () {
    Date::setTestNow('2026-10-21');

    $this->user = User::factory()->create();
});

test('every setup write tool description starts with the confirmation rule', function (string $tool) {
    expect(app($tool)->description())->toStartWith('Only call after the user confirmed a summary in chat.');
})->with([
    SaveOrchard::class,
    SaveSeason::class,
    AddTrees::class,
    UpdateTree::class,
    SetTreeRemoved::class,
    SaveHarvests::class,
]);

test('a farm can be set up from nothing', function () {
    $orchard = FarmServer::actingAs($this->user)
        ->tool(SaveOrchard::class, ['name' => 'สวนหน้าบ้าน', 'location' => 'จันทบุรี'])
        ->assertOk()
        ->payload()['orchard'];

    expect($orchard)->toMatchArray(['name' => 'สวนหน้าบ้าน', 'location' => 'จันทบุรี']);

    $season = FarmServer::actingAs($this->user)
        ->tool(SaveSeason::class, ['name' => 'ฤดู 2569', 'started_on' => '2026-01-01'])
        ->assertOk()
        ->payload()['season'];

    expect($season)->toMatchArray(['orchard_id' => $orchard['id'], 'name' => 'ฤดู 2569', 'started_on' => '2026-01-01', 'ended_on' => null]);

    $payload = FarmServer::actingAs($this->user)
        ->tool(AddTrees::class, ['prefix' => 'A-', 'count' => 3, 'variety' => 'หมอนทอง'])
        ->assertOk()
        ->payload();

    expect($payload['added'])->toBe(3)
        ->and(array_column($payload['trees'], 'code'))->toBe(['A-001', 'A-002', 'A-003'])
        ->and(Tree::where('orchard_id', $orchard['id'])->where('variety', 'หมอนทอง')->count())->toBe(3);
});

test('save orchard replaces an existing orchard', function () {
    $orchard = Orchard::factory()->create(['name' => 'เก่า', 'location' => 'ระยอง']);

    FarmServer::actingAs($this->user)
        ->tool(SaveOrchard::class, ['id' => $orchard->id, 'name' => 'ใหม่'])
        ->assertOk();

    expect($orchard->fresh())->name->toBe('ใหม่')->location->toBeNull();

    FarmServer::actingAs($this->user)
        ->tool(SaveOrchard::class, ['id' => 999999, 'name' => 'x'])
        ->assertHasErrors(['No orchard with id 999999 exists']);
});

test('save season closes a season and rejects a second open one or overlapping dates', function () {
    $orchard = Orchard::factory()->create();
    $open = Season::factory()->for($orchard)->create(['name' => 'ฤดู 2569', 'started_on' => '2026-01-01']);

    FarmServer::actingAs($this->user)
        ->tool(SaveSeason::class, ['name' => 'อีกรอบ', 'started_on' => '2026-06-01'])
        ->assertHasErrors(['already has an open season']);

    FarmServer::actingAs($this->user)
        ->tool(SaveSeason::class, ['id' => $open->id, 'name' => 'ฤดู 2569', 'started_on' => '2026-01-01', 'ended_on' => '2026-08-31'])
        ->assertOk();

    expect($open->fresh()->ended_on->toDateString())->toBe('2026-08-31');

    FarmServer::actingAs($this->user)
        ->tool(SaveSeason::class, ['name' => 'ซ้อน', 'started_on' => '2026-08-31'])
        ->assertHasErrors(['overlap another season']);

    FarmServer::actingAs($this->user)
        ->tool(SaveSeason::class, ['name' => 'ฤดู 2570', 'started_on' => '2026-09-01'])
        ->assertOk();

    expect($orchard->seasons()->count())->toBe(2);
});

test('save season cannot move a season to another orchard', function () {
    $season = Season::factory()->for(Orchard::factory())->create(['started_on' => '2026-01-01']);
    $other = Orchard::factory()->create();

    FarmServer::actingAs($this->user)
        ->tool(SaveSeason::class, ['id' => $season->id, 'orchard_id' => $other->id, 'name' => 'x', 'started_on' => '2026-01-01'])
        ->assertHasErrors(['cannot be moved to another orchard']);
});

test('add trees rejects codes that already exist and saves nothing', function () {
    $orchard = Orchard::factory()->create();
    Tree::factory()->for($orchard)->create(['code' => 'A-002']);

    FarmServer::actingAs($this->user)
        ->tool(AddTrees::class, ['tree_codes' => ['A-001', 'a-002']])
        ->assertHasErrors(['already exist', 'A-002']);

    FarmServer::actingAs($this->user)
        ->tool(AddTrees::class, ['tree_codes' => ['B-1', 'b-1']])
        ->assertHasErrors(['repeated codes']);

    FarmServer::actingAs($this->user)
        ->tool(AddTrees::class, ['tree_codes' => ['A-001'], 'prefix' => 'A-', 'count' => 1])
        ->assertHasErrors(['not both']);

    expect($orchard->trees()->count())->toBe(1);
});

test('add trees asks for an orchard when there is none', function () {
    FarmServer::actingAs($this->user)
        ->tool(AddTrees::class, ['tree_codes' => ['A-001']])
        ->assertHasErrors(['create one with save_orchard']);
});

test('update tree renames and replaces its details', function () {
    $orchard = Orchard::factory()->create();
    $tree = Tree::factory()->for($orchard)->create(['code' => 'A-001', 'variety' => 'ชะนี', 'notes' => 'เก่า']);
    Tree::factory()->for($orchard)->create(['code' => 'A-002']);

    FarmServer::actingAs($this->user)
        ->tool(UpdateTree::class, ['code' => 'A-001', 'new_code' => 'a-002'])
        ->assertHasErrors(['already has the code a-002']);

    FarmServer::actingAs($this->user)
        ->tool(UpdateTree::class, ['code' => 'A-001', 'new_code' => 'A-010', 'variety' => 'หมอนทอง', 'planted_on' => '2020-05-01'])
        ->assertOk();

    expect($tree->fresh())
        ->code->toBe('A-010')
        ->variety->toBe('หมอนทอง')
        ->notes->toBeNull()
        ->and($tree->fresh()->planted_on->toDateString())->toBe('2020-05-01');
});

test('set tree removed marks a tree gone and brings it back', function () {
    $tree = Tree::factory()->for(Orchard::factory())->create(['code' => 'A-001']);

    FarmServer::actingAs($this->user)
        ->tool(SetTreeRemoved::class, ['code' => 'A-001', 'removed' => true])
        ->assertOk();

    expect($tree->fresh()->removed_on->toDateString())->toBe('2026-10-21');

    FarmServer::actingAs($this->user)
        ->tool(SetTreeRemoved::class, ['code' => 'A-001', 'removed' => false])
        ->assertOk();

    expect($tree->fresh()->removed_on)->toBeNull();
});

test('save harvests records several trees in one call', function () {
    $orchard = Orchard::factory()->create();
    Tree::factory()->for($orchard)->create(['code' => 'A-001']);
    Tree::factory()->for($orchard)->create(['code' => 'A-002']);

    $payload = FarmServer::actingAs($this->user)
        ->tool(SaveHarvests::class, [
            'harvested_on' => '2026-10-20',
            'rows' => [
                ['tree_code' => 'A-001', 'weight_kg' => 12.5, 'fruit_count' => 4],
                ['tree_code' => 'A-002', 'weight_kg' => 8],
            ],
        ])
        ->assertOk()
        ->payload();

    expect($payload['saved'])->toBe(2)
        ->and($payload['total_weight_kg'])->toEqual(20.5)
        ->and(array_column($payload['harvests'], 'tree_code'))->toBe(['A-001', 'A-002'])
        ->and(Harvest::where('created_via', 'mcp')->count())->toBe(2);
});

test('save harvests saves nothing when a tree is removed or repeated', function () {
    $orchard = Orchard::factory()->create();
    Tree::factory()->for($orchard)->create(['code' => 'A-001']);
    Tree::factory()->for($orchard)->removed()->create(['code' => 'A-002']);

    FarmServer::actingAs($this->user)
        ->tool(SaveHarvests::class, ['harvested_on' => '2026-10-20', 'rows' => [
            ['tree_code' => 'A-001', 'weight_kg' => 10],
            ['tree_code' => 'A-002', 'weight_kg' => 10],
        ]])
        ->assertHasErrors(['A-002 have been removed']);

    FarmServer::actingAs($this->user)
        ->tool(SaveHarvests::class, ['harvested_on' => '2026-10-20', 'rows' => [
            ['tree_code' => 'A-001', 'weight_kg' => 10],
            ['tree_code' => 'a-001', 'weight_kg' => 10],
        ]])
        ->assertHasErrors(['may appear only once']);

    expect(Harvest::count())->toBe(0);
});

test('season summary totals harvests sales and linked expenses', function () {
    $orchard = Orchard::factory()->create(['name' => 'สวนหน้าบ้าน']);
    $season = Season::factory()->for($orchard)->create(['name' => 'ฤดู 2569', 'started_on' => '2026-01-01']);
    $a1 = Tree::factory()->for($orchard)->create(['code' => 'A-001']);
    $a2 = Tree::factory()->for($orchard)->create(['code' => 'A-002']);
    Harvest::factory()->for($a1)->create(['harvested_on' => '2026-06-01', 'weight_kg' => 30, 'fruit_count' => 10]);
    Harvest::factory()->for($a2)->create(['harvested_on' => '2026-06-01', 'weight_kg' => 10, 'fruit_count' => null]);
    Harvest::factory()->for($a1)->create(['harvested_on' => '2025-12-31', 'weight_kg' => 99]);
    Sale::factory()->for($orchard)->create(['sold_on' => '2026-06-02', 'weight_kg' => 40, 'amount' => 6000]);
    Expense::factory()->create(['season_id' => $season->id, 'category' => ExpenseCategory::Fertilizer, 'amount' => 1500]);
    Expense::factory()->create(['season_id' => null, 'amount' => 700]);

    $payload = FarmServer::actingAs($this->user)
        ->tool(GetSeasonSummary::class, ['season_id' => $season->id])
        ->assertOk()
        ->payload();

    expect($payload['season'])->toMatchArray(['id' => $season->id, 'name' => 'ฤดู 2569', 'orchard' => 'สวนหน้าบ้าน'])
        ->and($payload['harvest'])->toEqual([
            'total_weight_kg' => 40.0,
            'total_fruit_count' => 10,
            'harvested_tree_count' => 2,
            'avg_kg_per_harvested_tree' => 20.0,
        ])
        ->and($payload['sales'])->toEqual(['total_amount' => 6000.0, 'total_weight_kg' => 40.0, 'avg_price_per_kg' => 150.0])
        ->and($payload['expenses_total'])->toEqual(1500)
        ->and($payload['profit'])->toEqual(4500)
        ->and(array_column($payload['tree_yields'], 'tree_code'))->toBe(['A-001', 'A-002']);
});

test('season summary explains an unknown season id', function () {
    FarmServer::actingAs($this->user)
        ->tool(GetSeasonSummary::class, ['season_id' => 999999])
        ->assertHasErrors(['No season with id 999999 exists']);
});
