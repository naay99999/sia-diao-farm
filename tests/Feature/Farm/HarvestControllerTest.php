<?php

use App\Models\CareRecord;
use App\Models\Harvest;
use App\Models\Orchard;
use App\Models\Season;
use App\Models\Tree;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->orchard = Orchard::factory()->create();
});

test('single harvest stores and redirects to the tree', function () {
    $tree = Tree::factory()->for($this->orchard)->create();

    $this->actingAs($this->user)
        ->post(route('trees.harvests.store', $tree), [
            'harvested_on' => '2026-06-01', 'weight_kg' => '12.5', 'fruit_count' => 4,
        ])
        ->assertRedirect(route('trees.show', $tree));

    $this->assertDatabaseHas('harvests', ['tree_id' => $tree->id, 'weight_kg' => '12.50', 'fruit_count' => 4]);
});

test('harvest weight must be greater than zero', function () {
    $tree = Tree::factory()->for($this->orchard)->create();

    $this->actingAs($this->user)
        ->post(route('trees.harvests.store', $tree), ['harvested_on' => '2026-06-01', 'weight_kg' => 0])
        ->assertSessionHasErrors('weight_kg');
});

test('single harvest is rejected for a removed tree', function () {
    $tree = Tree::factory()->for($this->orchard)->removed()->create();

    $this->actingAs($this->user)
        ->post(route('trees.harvests.store', $tree), ['harvested_on' => '2026-06-01', 'weight_kg' => 5])
        ->assertSessionHasErrors('tree');

    expect(Harvest::count())->toBe(0);
});

test('harvest grid saves only filled rows', function () {
    [$a, $b, $c] = Tree::factory()->count(3)->for($this->orchard)->create();

    $this->actingAs($this->user)
        ->post(route('orchards.harvests.batch', $this->orchard), [
            'harvested_on' => '2026-06-01',
            'rows' => [
                ['tree_id' => $a->id, 'weight_kg' => '10', 'fruit_count' => '3'],
                ['tree_id' => $b->id, 'weight_kg' => '', 'fruit_count' => ''],
                ['tree_id' => $c->id, 'weight_kg' => '7.25', 'fruit_count' => ''],
            ],
        ])
        ->assertRedirect(route('orchards.show', $this->orchard));

    expect(Harvest::count())->toBe(2);
    $this->assertDatabaseHas('harvests', ['tree_id' => $c->id, 'weight_kg' => '7.25', 'fruit_count' => null]);
});

test('harvest grid with no filled rows fails', function () {
    $tree = Tree::factory()->for($this->orchard)->create();

    $this->actingAs($this->user)
        ->post(route('orchards.harvests.batch', $this->orchard), [
            'harvested_on' => '2026-06-01',
            'rows' => [['tree_id' => $tree->id, 'weight_kg' => '']],
        ])
        ->assertSessionHasErrors(['rows' => 'กรอกน้ำหนักอย่างน้อย 1 ต้น']);

    expect(Harvest::count())->toBe(0);
});

test('harvest grid rejects removed and other orchard trees', function () {
    $removed = Tree::factory()->for($this->orchard)->removed()->create();
    $foreign = Tree::factory()->create();

    $this->actingAs($this->user)
        ->post(route('orchards.harvests.batch', $this->orchard), [
            'harvested_on' => '2026-06-01',
            'rows' => [
                ['tree_id' => $removed->id, 'weight_kg' => '5'],
                ['tree_id' => $foreign->id, 'weight_kg' => '5'],
            ],
        ])
        ->assertSessionHasErrors(['rows.0.tree_id', 'rows.1.tree_id']);

    expect(Harvest::count())->toBe(0);
});

test('harvest grid create page lists active trees', function () {
    Tree::factory()->for($this->orchard)->create(['code' => 'A-1']);
    Tree::factory()->for($this->orchard)->removed()->create(['code' => 'A-2']);

    $this->actingAs($this->user)
        ->get(route('orchards.harvests.create', $this->orchard))
        ->assertInertia(fn (Assert $page) => $page
            ->component('farm/harvests/create')
            ->has('trees', 1));
});

test('update and destroy change a harvest', function () {
    $harvest = Harvest::factory()->create(['weight_kg' => 5]);

    $this->actingAs($this->user)
        ->patch(route('harvests.update', $harvest), ['harvested_on' => '2026-06-02', 'weight_kg' => 9])
        ->assertRedirect();
    expect($harvest->fresh()->weight_kg)->toBe('9.00');

    $this->actingAs($this->user)->delete(route('harvests.destroy', $harvest))->assertRedirect();
    $this->assertModelMissing($harvest);
});

test('a tree with a harvest or care record cannot be deleted', function () {
    $tree = Tree::factory()->for($this->orchard)->create();
    expect($tree->hasRecords())->toBeFalse();

    Harvest::factory()->for($tree)->create();
    expect($tree->hasRecords())->toBeTrue();

    $this->actingAs($this->user)
        ->delete(route('trees.destroy', $tree))
        ->assertSessionHasErrors(['tree' => 'ลบไม่ได้เพราะต้นนี้มีบันทึกแล้ว']);
    $this->assertModelExists($tree);

    $other = Tree::factory()->for($this->orchard)->create();
    CareRecord::factory()->for($this->orchard)->create()->trees()->sync([$other->id]);
    expect($other->hasRecords())->toBeTrue();
});

test('tree show timeline merges care and harvests newest first', function () {
    $tree = Tree::factory()->for($this->orchard)->create();
    $care = CareRecord::factory()->for($this->orchard)->create(['performed_on' => '2026-03-01', 'product' => '15-15-15']);
    $care->trees()->sync([$tree->id]);
    Harvest::factory()->for($tree)->create(['harvested_on' => '2026-06-01', 'weight_kg' => 10]);
    Harvest::factory()->for($tree)->create(['harvested_on' => '2026-01-01', 'weight_kg' => 3]);

    $this->actingAs($this->user)
        ->get(route('trees.show', $tree))
        ->assertInertia(fn (Assert $page) => $page
            ->where('timeline.0.kind', 'harvest')
            ->where('timeline.1.kind', 'care')
            ->where('timeline.1.title', 'ใส่ปุ๋ย 15-15-15')
            ->where('timeline.2.date', '2026-01-01')
            ->has('timeline', 3));
});

test('season yields sum only harvests inside each season', function () {
    $tree = Tree::factory()->for($this->orchard)->create();
    $season = Season::factory()->for($this->orchard)->create([
        'name' => 'รอบ 2569', 'started_on' => '2026-05-01', 'ended_on' => '2026-07-31',
    ]);
    Harvest::factory()->for($tree)->create(['harvested_on' => '2026-05-01', 'weight_kg' => 10, 'fruit_count' => 2]);
    Harvest::factory()->for($tree)->create(['harvested_on' => '2026-07-31', 'weight_kg' => 5.5, 'fruit_count' => 1]);
    Harvest::factory()->for($tree)->create(['harvested_on' => '2026-08-01', 'weight_kg' => 99]);

    $this->actingAs($this->user)
        ->get(route('trees.show', $tree))
        ->assertInertia(fn (Assert $page) => $page
            ->where('seasonYields.0.seasonId', $season->id)
            ->where('seasonYields.0.weightKg', 15.5)
            ->where('seasonYields.0.fruitCount', 3));
});
