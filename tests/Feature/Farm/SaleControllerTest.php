<?php

use App\Models\Orchard;
use App\Models\Sale;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->orchard = Orchard::factory()->create();
});

test('sale stores and redirects to the orchard', function () {
    $this->actingAs($this->user)
        ->post(route('orchards.sales.store', $this->orchard), [
            'sold_on' => '2026-06-10', 'buyer_name' => 'พ่อค้า', 'weight_kg' => '120.5', 'amount' => '24100',
        ])
        ->assertRedirect(route('orchards.show', $this->orchard));

    $this->assertDatabaseHas('sales', [
        'orchard_id' => $this->orchard->id, 'buyer_name' => 'พ่อค้า', 'weight_kg' => '120.50', 'amount' => '24100.00',
    ]);
});

test('buyer name is optional', function () {
    $this->actingAs($this->user)
        ->post(route('orchards.sales.store', $this->orchard), [
            'sold_on' => '2026-06-10', 'weight_kg' => 10, 'amount' => 1000,
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('sales', ['orchard_id' => $this->orchard->id, 'buyer_name' => null]);
});

test('weight and amount are required and greater than zero', function (array $data, array $errors) {
    $this->actingAs($this->user)
        ->post(route('orchards.sales.store', $this->orchard), ['sold_on' => '2026-06-10', ...$data])
        ->assertSessionHasErrors($errors);

    expect(Sale::count())->toBe(0);
})->with([
    'missing both' => [[], ['weight_kg', 'amount']],
    'zero weight' => [['weight_kg' => 0, 'amount' => 100], ['weight_kg']],
    'zero amount' => [['weight_kg' => 10, 'amount' => 0], ['amount']],
    'negative amount' => [['weight_kg' => 10, 'amount' => -5], ['amount']],
]);

test('sale updates', function () {
    $sale = Sale::factory()->for($this->orchard)->create();

    $this->actingAs($this->user)
        ->patch(route('sales.update', $sale), [
            'sold_on' => '2026-07-01', 'weight_kg' => 50, 'amount' => 5000,
        ])
        ->assertRedirect(route('orchards.show', $this->orchard));

    expect($sale->refresh()->amount)->toBe('5000.00')
        ->and($sale->weight_kg)->toBe('50.00');
});

test('sale update validates', function () {
    $sale = Sale::factory()->for($this->orchard)->create();

    $this->actingAs($this->user)
        ->patch(route('sales.update', $sale), ['sold_on' => '2026-07-01', 'weight_kg' => 0, 'amount' => 10])
        ->assertSessionHasErrors('weight_kg');
});

test('sale destroys', function () {
    $sale = Sale::factory()->for($this->orchard)->create();

    $this->actingAs($this->user)
        ->delete(route('sales.destroy', $sale))
        ->assertRedirect(route('orchards.show', $this->orchard));

    $this->assertModelMissing($sale);
});

test('orchard page lists sales newest first', function () {
    Sale::factory()->for($this->orchard)->create(['sold_on' => '2026-01-01']);
    Sale::factory()->for($this->orchard)->create(['sold_on' => '2026-05-01']);
    Sale::factory()->create(['sold_on' => '2026-09-01']);

    $this->actingAs($this->user)
        ->get(route('orchards.show', $this->orchard))
        ->assertInertia(fn (Assert $page) => $page
            ->has('sales', 2)
            ->where('sales.0.sold_on', fn ($value) => str_starts_with($value, '2026-05-01')));
});
