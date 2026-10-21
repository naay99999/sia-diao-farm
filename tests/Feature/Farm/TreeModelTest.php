<?php

use App\Models\Orchard;
use App\Models\Tree;

test('active scope excludes removed trees', function () {
    $orchard = Orchard::factory()->create();
    $active = Tree::factory()->for($orchard)->create();
    Tree::factory()->for($orchard)->removed()->create();

    expect(Tree::active()->pluck('id')->all())->toBe([$active->id]);
});

test('isRemoved reflects removed_on', function () {
    expect(Tree::factory()->make()->isRemoved())->toBeFalse()
        ->and(Tree::factory()->removed()->make()->isRemoved())->toBeTrue();
});

test('orchard has many trees', function () {
    $orchard = Orchard::factory()->create();
    Tree::factory()->count(2)->for($orchard)->create();

    expect($orchard->trees)->toHaveCount(2);
});
