<?php

use App\Models\FruitType;
use App\Models\FruitVariety;
use App\Models\User;

test('guests cannot view fruit varieties', function () {
    $this->get(route('fruit-varieties.index'))->assertRedirect(route('login'));
});

test('a user can view the varieties page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('fruit-varieties.index'))
        ->assertOk();
});

test('a user can create a variety', function () {
    $type = FruitType::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('fruit-varieties.store'), [
            'fruit_type_id' => $type->id,
            'name' => 'หมอนทอง',
            'days_to_harvest' => 135,
        ])
        ->assertRedirect(route('fruit-varieties.index'));

    expect(FruitVariety::where('name', 'หมอนทอง')->exists())->toBeTrue();
});

test('variety validation requires type, name and positive days', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('fruit-varieties.index'))
        ->post(route('fruit-varieties.store'), [
            'fruit_type_id' => 999,
            'name' => '',
            'days_to_harvest' => 0,
        ])
        ->assertSessionHasErrors(['fruit_type_id', 'name', 'days_to_harvest']);
});

test('a user can update a variety', function () {
    $variety = FruitVariety::factory()->create(['days_to_harvest' => 100]);

    $this->actingAs(User::factory()->create())
        ->put(route('fruit-varieties.update', $variety), [
            'fruit_type_id' => $variety->fruit_type_id,
            'name' => $variety->name,
            'days_to_harvest' => 120,
        ])
        ->assertRedirect(route('fruit-varieties.index'));

    expect($variety->refresh()->days_to_harvest)->toBe(120);
});

test('a user can delete a variety', function () {
    $variety = FruitVariety::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('fruit-varieties.destroy', $variety))
        ->assertRedirect(route('fruit-varieties.index'));

    expect(FruitVariety::find($variety->id))->toBeNull();
});
