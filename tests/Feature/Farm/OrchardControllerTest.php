<?php

use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\Harvest;
use App\Models\Observation;
use App\Models\Orchard;
use App\Models\Sale;
use App\Models\Season;
use App\Models\Tree;
use App\Models\TreeIssue;
use App\Models\User;

test('guests are redirected to login from orchards index', function () {
    $this->get(route('orchards.index'))->assertRedirect(route('login'));
});

test('home redirects to orchards', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect(route('orchards.index'));
});

test('home redirects guests to login', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('orchards index lists orchards', function () {
    Orchard::factory()->count(2)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('orchards.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('farm/orchards/index')
            ->has('orchards', 2));
});

test('store creates an orchard and redirects to show', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('orchards.store'), ['name' => 'สวนบ้านล่าง'])
        ->assertRedirect(route('orchards.show', Orchard::first()));

    $this->assertDatabaseHas('orchards', ['name' => 'สวนบ้านล่าง']);
});

test('store requires a name', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('orchards.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('show renders the orchard', function () {
    $orchard = Orchard::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('orchards.show', $orchard))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('farm/orchards/show')
            ->where('orchard.id', $orchard->id));
});

test('update changes the orchard', function () {
    $orchard = Orchard::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('orchards.update', $orchard), ['name' => 'สวนใหม่', 'location' => 'จันทบุรี'])
        ->assertRedirect(route('orchards.show', $orchard));

    expect($orchard->fresh())->name->toBe('สวนใหม่')->location->toBe('จันทบุรี');
});

test('destroy removes an orchard', function () {
    $orchard = Orchard::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('orchards.destroy', $orchard))
        ->assertRedirect(route('orchards.index'));

    $this->assertModelMissing($orchard);
});

test('destroy removes an orchard whose trees and seasons have no records', function () {
    $orchard = Orchard::factory()->create();
    Tree::factory()->for($orchard)->create();
    Season::factory()->for($orchard)->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('orchards.destroy', $orchard))
        ->assertRedirect(route('orchards.index'));

    $this->assertModelMissing($orchard);
});

test('destroy refuses an orchard that has records', function (Closure $recordFor) {
    $orchard = Orchard::factory()->create();
    $recordFor($orchard)->delete();

    $this->actingAs(User::factory()->create())
        ->from(route('orchards.show', $orchard))
        ->delete(route('orchards.destroy', $orchard))
        ->assertRedirect(route('orchards.show', $orchard))
        ->assertSessionHasErrors(['orchard' => 'ลบไม่ได้เพราะสวนนี้มีบันทึกแล้ว']);

    $this->assertModelExists($orchard);
})->with([
    'harvest' => fn (Orchard $orchard) => Harvest::factory()->for(Tree::factory()->for($orchard))->create(),
    'observation' => fn (Orchard $orchard) => Observation::factory()->for(Tree::factory()->for($orchard))->create(),
    'tree issue' => fn (Orchard $orchard) => TreeIssue::factory()->for(Tree::factory()->for($orchard))->create(),
    'care record' => fn (Orchard $orchard) => CareRecord::factory()->for($orchard)->create(),
    'sale' => fn (Orchard $orchard) => Sale::factory()->for($orchard)->create(),
    'season expense' => fn (Orchard $orchard) => Expense::factory()->for(Season::factory()->for($orchard))->create(),
]);

test('orchards index shows active tree count and open-season yield only', function () {
    $orchard = Orchard::factory()->create(['name' => 'ก']);
    $bare = Orchard::factory()->create(['name' => 'ข']);
    Season::factory()->for($orchard)->create(['name' => 'เก่า', 'started_on' => '2025-01-01', 'ended_on' => '2025-12-31']);
    Season::factory()->for($orchard)->create(['name' => 'ปัจจุบัน', 'started_on' => '2026-01-01']);
    $active = Tree::factory()->for($orchard)->create();
    $removed = Tree::factory()->for($orchard)->removed()->create();

    Harvest::factory()->for($active)->create(['harvested_on' => '2025-06-01', 'weight_kg' => 100]);
    Harvest::factory()->for($active)->create(['harvested_on' => '2026-02-01', 'weight_kg' => 12.5]);
    Harvest::factory()->for($removed)->create(['harvested_on' => '2026-03-01', 'weight_kg' => 7.5]);
    Harvest::factory()->for($removed)->create(['harvested_on' => '2026-01-01', 'weight_kg' => 5]);
    Harvest::factory()->create(['harvested_on' => '2026-03-01', 'weight_kg' => 50]);

    $this->actingAs(User::factory()->create())
        ->get(route('orchards.index'))
        ->assertInertia(fn ($page) => $page
            ->where('orchards.0.id', $orchard->id)
            ->where('orchards.0.trees_count', 1)
            ->where('orchards.0.current_season', 'ปัจจุบัน')
            ->where('orchards.0.current_yield_kg', 25)
            ->where('orchards.1.id', $bare->id)
            ->where('orchards.1.trees_count', 0)
            ->where('orchards.1.current_season', null)
            ->where('orchards.1.current_yield_kg', 0));
});

test('orchard show gives current season yield per tree', function () {
    $orchard = Orchard::factory()->create();
    Season::factory()->for($orchard)->create(['started_on' => '2026-01-01']);
    $tree = Tree::factory()->for($orchard)->create();
    $idle = Tree::factory()->for($orchard)->create();
    Harvest::factory()->for($tree)->create(['harvested_on' => '2026-02-01', 'weight_kg' => 4]);
    Harvest::factory()->for($tree)->create(['harvested_on' => '2025-02-01', 'weight_kg' => 90]);

    $this->actingAs(User::factory()->create())
        ->get(route('orchards.show', $orchard))
        ->assertInertia(fn ($page) => $page
            ->where("currentSeasonYieldKg.{$tree->id}", 4)
            ->missing("currentSeasonYieldKg.{$idle->id}"));
});
