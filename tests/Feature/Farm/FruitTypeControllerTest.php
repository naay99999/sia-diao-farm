<?php

use App\Models\FruitType;
use App\Models\User;

test('guests cannot view fruit types', function () {
    $this->get(route('fruit-types.index'))->assertRedirect(route('login'));
});

test('a user can view the fruit types page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('fruit-types.index'))
        ->assertOk();
});

test('a user can create a fruit type', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('fruit-types.store'), ['name' => 'ทุเรียน'])
        ->assertRedirect(route('fruit-types.index'));

    expect(FruitType::where('name', 'ทุเรียน')->exists())->toBeTrue();
});

test('a fruit type name is required', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('fruit-types.index'))
        ->post(route('fruit-types.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('a user can update a fruit type', function () {
    $type = FruitType::factory()->create(['name' => 'มะม่วง']);

    $this->actingAs(User::factory()->create())
        ->put(route('fruit-types.update', $type), ['name' => 'มะม่วงเขียวเสวย'])
        ->assertRedirect(route('fruit-types.index'));

    expect($type->refresh()->name)->toBe('มะม่วงเขียวเสวย');
});

test('a user can delete a fruit type', function () {
    $type = FruitType::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('fruit-types.destroy', $type))
        ->assertRedirect(route('fruit-types.index'));

    expect(FruitType::find($type->id))->toBeNull();
});
