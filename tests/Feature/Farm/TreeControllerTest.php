<?php

use App\Models\Orchard;
use App\Models\Tree;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('store creates a single tree', function () {
    $orchard = Orchard::factory()->create();

    $this->actingAs($this->user)
        ->post(route('orchards.trees.store', $orchard), ['code' => 'A-001', 'variety' => 'หมอนทอง'])
        ->assertRedirect(route('orchards.show', $orchard));

    $this->assertDatabaseHas('trees', ['orchard_id' => $orchard->id, 'code' => 'A-001', 'variety' => 'หมอนทอง']);
});

test('batch creates zero padded codes', function () {
    $orchard = Orchard::factory()->create();

    $this->actingAs($this->user)
        ->post(route('orchards.trees.batch', $orchard), ['prefix' => 'A-', 'start' => 1, 'count' => 3])
        ->assertRedirect(route('orchards.show', $orchard));

    expect($orchard->trees()->orderBy('code')->pluck('code')->all())->toBe(['A-001', 'A-002', 'A-003']);
});

test('batch fails entirely when a code already exists', function () {
    $orchard = Orchard::factory()->create();
    Tree::factory()->for($orchard)->create(['code' => 'A-002']);

    $this->actingAs($this->user)
        ->post(route('orchards.trees.batch', $orchard), ['prefix' => 'A-', 'start' => 1, 'count' => 3])
        ->assertSessionHasErrors('prefix');

    expect($orchard->trees()->count())->toBe(1);
});

test('batch validates count bounds', function () {
    $orchard = Orchard::factory()->create();

    $this->actingAs($this->user)
        ->post(route('orchards.trees.batch', $orchard), ['prefix' => 'A-', 'start' => 1, 'count' => 501])
        ->assertSessionHasErrors('count');
});

test('code is unique per orchard but may repeat in another orchard', function () {
    $orchard = Orchard::factory()->create();
    $other = Orchard::factory()->create();
    Tree::factory()->for($orchard)->create(['code' => 'A-001']);

    $this->actingAs($this->user)
        ->post(route('orchards.trees.store', $orchard), ['code' => 'A-001'])
        ->assertSessionHasErrors('code');

    $this->actingAs($this->user)
        ->post(route('orchards.trees.store', $other), ['code' => 'A-001'])
        ->assertSessionDoesntHaveErrors();

    expect(Tree::where('code', 'A-001')->count())->toBe(2);
});

test('update changes the tree and ignores its own code in uniqueness', function () {
    $tree = Tree::factory()->create(['code' => 'A-001']);

    $this->actingAs($this->user)
        ->patch(route('trees.update', $tree), ['code' => 'A-001', 'variety' => 'ก้านยาว'])
        ->assertRedirect(route('trees.show', $tree));

    expect($tree->fresh()->variety)->toBe('ก้านยาว');
});

test('show renders the tree with its orchard', function () {
    $tree = Tree::factory()->create();

    $this->actingAs($this->user)
        ->get(route('trees.show', $tree))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('farm/trees/show')
            ->where('tree.id', $tree->id)
            ->where('tree.orchard.id', $tree->orchard_id));
});

test('scanning a QR while logged out returns to the tree after login', function () {
    $user = User::factory()->create(['password' => 'password']);
    $tree = Tree::factory()->create();

    $this->get('/t/'.$tree->id)->assertRedirect(route('login'));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('trees.qr', $tree));

    $this->get(route('trees.qr', $tree))->assertRedirect(route('trees.show', $tree));
});

test('scanning a QR while logged in redirects to the tree', function () {
    $tree = Tree::factory()->create();

    $this->actingAs($this->user)
        ->get(route('trees.qr', $tree))
        ->assertRedirect(route('trees.show', $tree));
});

test('remove sets removed_on and hides the tree from the orchard unless requested', function () {
    $orchard = Orchard::factory()->create();
    $tree = Tree::factory()->for($orchard)->create();
    Tree::factory()->for($orchard)->create();

    $this->actingAs($this->user)
        ->patch(route('trees.remove', $tree), ['removed_on' => '2026-03-01'])
        ->assertRedirect(route('trees.show', $tree));

    expect($tree->fresh()->removed_on->toDateString())->toBe('2026-03-01');

    $this->actingAs($this->user)
        ->get(route('orchards.show', $orchard))
        ->assertInertia(fn ($page) => $page->has('trees', 1)->where('showRemoved', false));

    $this->actingAs($this->user)
        ->get(route('orchards.show', ['orchard' => $orchard, 'show_removed' => 1]))
        ->assertInertia(fn ($page) => $page->has('trees', 2)->where('showRemoved', true));
});

test('remove defaults to today', function () {
    $tree = Tree::factory()->create();

    $this->actingAs($this->user)->patch(route('trees.remove', $tree))->assertRedirect();

    expect($tree->fresh()->removed_on->toDateString())->toBe(now()->toDateString());
});

test('restore clears removed_on', function () {
    $tree = Tree::factory()->removed()->create();

    $this->actingAs($this->user)
        ->patch(route('trees.restore', $tree))
        ->assertRedirect(route('trees.show', $tree));

    expect($tree->fresh()->removed_on)->toBeNull();
});

test('destroy from the tree page redirects to the orchard', function () {
    $tree = Tree::factory()->create();

    $this->actingAs($this->user)
        ->from(route('trees.show', $tree))
        ->delete(route('trees.destroy', $tree))
        ->assertRedirect(route('orchards.show', $tree->orchard_id));
});

test('destroy deletes a tree without records', function () {
    $tree = Tree::factory()->create();

    $this->actingAs($this->user)
        ->delete(route('trees.destroy', $tree))
        ->assertRedirect(route('orchards.show', $tree->orchard_id));

    $this->assertModelMissing($tree);
});
