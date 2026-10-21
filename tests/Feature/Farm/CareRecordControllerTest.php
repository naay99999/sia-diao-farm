<?php

use App\Enums\CareType;
use App\Models\CareRecord;
use App\Models\Orchard;
use App\Models\Tree;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->orchard = Orchard::factory()->create();
});

function careRecordPayload(array $treeIds, array $overrides = []): array
{
    return array_merge([
        'type' => 'fertilizer',
        'performed_on' => '2026-10-20',
        'product' => '15-15-15',
        'quantity' => '2 กก.',
        'tree_ids' => $treeIds,
    ], $overrides);
}

test('create page lists active trees only', function () {
    Tree::factory()->for($this->orchard)->create(['code' => 'A-001']);
    Tree::factory()->for($this->orchard)->removed()->create(['code' => 'A-002']);

    $this->actingAs($this->user)
        ->get(route('orchards.care-records.create', $this->orchard))
        ->assertInertia(fn ($page) => $page
            ->component('farm/care-records/create')
            ->has('trees', 1)
            ->has('types', 3));
});

test('bulk record creates one care record with pivot rows', function () {
    $trees = Tree::factory()->count(3)->for($this->orchard)->create();

    $this->actingAs($this->user)
        ->post(route('orchards.care-records.store', $this->orchard), careRecordPayload($trees->pluck('id')->all()))
        ->assertRedirect(route('orchards.show', $this->orchard));

    expect(CareRecord::count())->toBe(1);
    $record = CareRecord::first();
    expect($record->type)->toBe(CareType::Fertilizer)
        ->and($record->product)->toBe('15-15-15')
        ->and($record->trees()->count())->toBe(3);
    $this->assertDatabaseCount('care_record_tree', 3);
});

test('single tree record from tree page redirects to the tree', function () {
    $tree = Tree::factory()->for($this->orchard)->create();

    $this->actingAs($this->user)
        ->post(route('orchards.care-records.store', $this->orchard), careRecordPayload([$tree->id], ['return_to' => 'tree']))
        ->assertRedirect(route('trees.show', $tree));

    expect($tree->careRecords()->count())->toBe(1);
});

test('return_to tree with several trees redirects to the orchard', function () {
    $trees = Tree::factory()->count(2)->for($this->orchard)->create();

    $this->actingAs($this->user)
        ->post(route('orchards.care-records.store', $this->orchard), careRecordPayload($trees->pluck('id')->all(), ['return_to' => 'tree']))
        ->assertRedirect(route('orchards.show', $this->orchard));
});

test('bulk care rejects removed trees and trees from other orchards', function () {
    $removed = Tree::factory()->for($this->orchard)->removed()->create();
    $foreign = Tree::factory()->create();

    foreach ([$removed, $foreign] as $tree) {
        $this->actingAs($this->user)
            ->post(route('orchards.care-records.store', $this->orchard), careRecordPayload([$tree->id]))
            ->assertSessionHasErrors('tree_ids.0');
    }

    expect(CareRecord::count())->toBe(0);
    $this->assertDatabaseCount('care_record_tree', 0);
});

test('empty tree_ids and invalid type are rejected', function () {
    $this->actingAs($this->user)
        ->post(route('orchards.care-records.store', $this->orchard), careRecordPayload([]))
        ->assertSessionHasErrors('tree_ids');

    $tree = Tree::factory()->for($this->orchard)->create();
    $this->actingAs($this->user)
        ->post(route('orchards.care-records.store', $this->orchard), careRecordPayload([$tree->id], ['type' => 'bogus']))
        ->assertSessionHasErrors('type');

    expect(CareRecord::count())->toBe(0);
});

test('update replaces the tree set', function () {
    [$a, $b, $c] = Tree::factory()->count(3)->for($this->orchard)->create()->all();
    $record = CareRecord::factory()->for($this->orchard)->create();
    $record->trees()->sync([$a->id, $b->id]);

    $this->actingAs($this->user)
        ->patch(route('care-records.update', $record), careRecordPayload([$b->id, $c->id], ['type' => 'pesticide']))
        ->assertRedirect(route('orchards.show', $this->orchard));

    expect($record->fresh()->type)->toBe(CareType::Pesticide)
        ->and($record->trees()->pluck('trees.id')->sort()->values()->all())->toBe([$b->id, $c->id]);
});

test('update validates trees against the record orchard', function () {
    $foreign = Tree::factory()->create();
    $record = CareRecord::factory()->for($this->orchard)->create();

    $this->actingAs($this->user)
        ->patch(route('care-records.update', $record), careRecordPayload([$foreign->id]))
        ->assertSessionHasErrors('tree_ids.0');
});

test('destroy removes the record and pivot rows', function () {
    $tree = Tree::factory()->for($this->orchard)->create();
    $record = CareRecord::factory()->for($this->orchard)->create();
    $record->trees()->sync([$tree->id]);

    $this->actingAs($this->user)
        ->from(route('trees.show', $tree))
        ->delete(route('care-records.destroy', $record))
        ->assertRedirect(route('trees.show', $tree));

    expect(CareRecord::count())->toBe(0);
    $this->assertSoftDeleted($record);
    $this->assertDatabaseCount('care_record_tree', 1);
});

test('update keeps an already attached removed tree but rejects newly added removed trees', function () {
    $active = Tree::factory()->for($this->orchard)->create();
    $attachedRemoved = Tree::factory()->for($this->orchard)->removed()->create();
    $otherRemoved = Tree::factory()->for($this->orchard)->removed()->create();
    $record = CareRecord::factory()->for($this->orchard)->create();
    $record->trees()->sync([$active->id, $attachedRemoved->id]);

    $this->actingAs($this->user)
        ->patch(route('care-records.update', $record), careRecordPayload([$active->id, $attachedRemoved->id], ['product' => 'ใหม่', 'return_to' => 'tree']))
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    expect($record->trees()->pluck('trees.id')->sort()->values()->all())
        ->toBe(collect([$active->id, $attachedRemoved->id])->sort()->values()->all());

    $this->actingAs($this->user)
        ->patch(route('care-records.update', $record), careRecordPayload([$active->id, $otherRemoved->id]))
        ->assertSessionHasErrors('tree_ids.1');
});

test('multi tree update from the tree page returns back', function () {
    $trees = Tree::factory()->count(2)->for($this->orchard)->create();
    $record = CareRecord::factory()->for($this->orchard)->create();
    $record->trees()->sync($trees->pluck('id'));

    $this->actingAs($this->user)
        ->from(route('trees.show', $trees[0]))
        ->patch(route('care-records.update', $record), careRecordPayload($trees->pluck('id')->all(), ['return_to' => 'tree']))
        ->assertRedirect(route('trees.show', $trees[0]));
});
