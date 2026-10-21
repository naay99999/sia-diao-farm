<?php

use App\Models\Harvest;
use App\Models\Orchard;
use App\Models\Season;
use App\Models\Tree;
use App\Models\User;
use App\Support\Provenance;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->orchard = Orchard::factory()->create();
});

test('records created in app store creator and via app', function () {
    $tree = Tree::factory()->for($this->orchard)->create();

    $this->actingAs($this->user)
        ->post(route('trees.harvests.store', $tree), ['harvested_on' => '2026-06-01', 'weight_kg' => 10])
        ->assertRedirect();

    $harvest = Harvest::query()->firstOrFail();

    expect($harvest->created_by)->toBe($this->user->id)
        ->and($harvest->created_via)->toBe('app')
        ->and($harvest->updated_by)->toBe($this->user->id)
        ->and($harvest->updated_via)->toBe('app')
        ->and($harvest->isFromAi())->toBeFalse();
});

test('records created under mcp context store via mcp', function () {
    Context::add(Provenance::VIA_KEY, 'mcp');

    $harvest = Harvest::factory()->create();

    expect($harvest->fresh()->created_via)->toBe('mcp')
        ->and($harvest->isFromAi())->toBeTrue();
});

test('updating sets updated_by and updated_via only', function () {
    $harvest = Harvest::factory()->create();

    $this->actingAs($this->user);
    Context::add(Provenance::VIA_KEY, 'mcp');
    $harvest->update(['weight_kg' => 99]);

    $harvest = $harvest->fresh();

    expect($harvest->created_via)->toBe('app')
        ->and($harvest->created_by)->toBeNull()
        ->and($harvest->updated_via)->toBe('mcp')
        ->and($harvest->updated_by)->toBe($this->user->id)
        ->and($harvest->isFromAi())->toBeTrue();
});

test('deleting a harvest soft deletes it', function () {
    $harvest = Harvest::factory()->create();

    $this->actingAs($this->user)->delete(route('harvests.destroy', $harvest))->assertRedirect();

    $this->assertSoftDeleted($harvest);
});

test('soft deleted harvests are excluded from orchard and season yields', function () {
    Date::setTestNow('2026-06-15');
    Season::factory()->for($this->orchard)->create(['started_on' => '2026-05-01', 'ended_on' => null]);
    $tree = Tree::factory()->for($this->orchard)->create();
    Harvest::factory()->for($tree)->create(['harvested_on' => '2026-06-01', 'weight_kg' => 10]);
    Harvest::factory()->for($tree)->create(['harvested_on' => '2026-06-02', 'weight_kg' => 5])->delete();

    $this->actingAs($this->user)
        ->get(route('orchards.index'))
        ->assertInertia(fn (Assert $page) => $page->where('orchards.0.current_yield_kg', 10));

    $this->actingAs($this->user)
        ->get(route('trees.show', $tree))
        ->assertInertia(fn (Assert $page) => $page->where('seasonYields.0.weightKg', 10));

    $this->actingAs($this->user)
        ->get(route('orchards.show', $this->orchard))
        ->assertInertia(fn (Assert $page) => $page->where("currentSeasonYieldKg.{$tree->id}", 10));

    $season = $this->orchard->currentSeason;

    $this->actingAs($this->user)
        ->get(route('seasons.show', $season))
        ->assertInertia(fn (Assert $page) => $page
            ->where('totalYieldKg', 10)
            ->where('treeYields.0.weightKg', 10));
});

test('a tree whose records were soft deleted still counts as having records', function () {
    $tree = Tree::factory()->for($this->orchard)->create();
    Harvest::factory()->for($tree)->create()->delete();

    expect($tree->hasRecords())->toBeTrue();
});
