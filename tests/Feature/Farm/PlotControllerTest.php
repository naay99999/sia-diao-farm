<?php

use App\Models\FruitVariety;
use App\Models\Plot;
use App\Models\User;

test('guests cannot view plots', function () {
    $this->get(route('plots.index'))->assertRedirect(route('login'));
});

test('a user can view the plots list', function () {
    Plot::factory()->count(2)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('plots.index'))
        ->assertOk();
});

test('a user can view the create plot page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('plots.create'))
        ->assertOk();
});

test('a user can create a plot', function () {
    $variety = FruitVariety::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('plots.store'), [
            'name' => 'แปลงทุเรียนทิศเหนือ',
            'fruit_variety_id' => $variety->id,
            'tree_count' => 50,
            'planted_at' => '2020-05-01',
            'area_rai' => 5.5,
            'notes' => null,
        ])
        ->assertRedirect(route('plots.index'));

    expect(Plot::where('name', 'แปลงทุเรียนทิศเหนือ')->exists())->toBeTrue();
});

test('plot validation requires name, variety and tree count', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('plots.create'))
        ->post(route('plots.store'), [
            'name' => '',
            'fruit_variety_id' => 999,
            'tree_count' => 0,
        ])
        ->assertSessionHasErrors(['name', 'fruit_variety_id', 'tree_count']);
});

test('a user can view a single plot', function () {
    $plot = Plot::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('plots.show', $plot))
        ->assertOk();
});

test('a user can view the edit plot page', function () {
    $plot = Plot::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('plots.edit', $plot))
        ->assertOk();
});

test('a user can update a plot', function () {
    $plot = Plot::factory()->create(['tree_count' => 10]);

    $this->actingAs(User::factory()->create())
        ->put(route('plots.update', $plot), [
            'name' => $plot->name,
            'fruit_variety_id' => $plot->fruit_variety_id,
            'tree_count' => 80,
            'planted_at' => null,
            'area_rai' => null,
            'notes' => null,
        ])
        ->assertRedirect(route('plots.show', $plot));

    expect($plot->refresh()->tree_count)->toBe(80);
});

test('a user can delete a plot', function () {
    $plot = Plot::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('plots.destroy', $plot))
        ->assertRedirect(route('plots.index'));

    expect(Plot::find($plot->id))->toBeNull();
});
