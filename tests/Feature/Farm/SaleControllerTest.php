<?php

use App\Models\CropCycle;
use App\Models\Grade;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;

test('guests cannot record a sale', function () {
    $cycle = CropCycle::factory()->create();

    $this->post(route('crop-cycles.sales.store', $cycle), [])
        ->assertRedirect(route('login'));
});

test('a user can record a sale and the subtotal is computed server-side', function () {
    $cycle = CropCycle::factory()->create();
    $grade = Grade::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('crop-cycles.sales.store', $cycle), [
            'buyer_name' => 'ล้งเจ๊แดง',
            'sold_on' => '2026-05-10',
            'items' => [
                ['grade_id' => $grade->id, 'weight_kg' => 100, 'price_per_kg' => 85.5],
            ],
        ])
        ->assertRedirect(route('crop-cycles.show', $cycle));

    $sale = Sale::first();
    expect($sale->crop_cycle_id)->toBe($cycle->id);
    expect($sale->recorded_by)->toBe($user->id);

    $item = SaleItem::first();
    expect($item->subtotal)->toBe('8550.00');
});

test('a sale requires a buyer, date and at least one item', function () {
    $cycle = CropCycle::factory()->create();

    $this->actingAs(User::factory()->create())
        ->from(route('crop-cycles.show', $cycle))
        ->post(route('crop-cycles.sales.store', $cycle), [
            'buyer_name' => '',
            'sold_on' => '',
            'items' => [],
        ])
        ->assertSessionHasErrors(['buyer_name', 'sold_on', 'items']);
});

test('sale items require grade, weight and price', function () {
    $cycle = CropCycle::factory()->create();

    $this->actingAs(User::factory()->create())
        ->from(route('crop-cycles.show', $cycle))
        ->post(route('crop-cycles.sales.store', $cycle), [
            'buyer_name' => 'ล้ง',
            'sold_on' => '2026-05-10',
            'items' => [
                ['grade_id' => 999, 'weight_kg' => 0, 'price_per_kg' => 0],
            ],
        ])
        ->assertSessionHasErrors(['items.0.grade_id', 'items.0.weight_kg', 'items.0.price_per_kg']);
});

test('a user can delete a sale', function () {
    $sale = Sale::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('sales.destroy', $sale))
        ->assertRedirect();

    expect(Sale::find($sale->id))->toBeNull();
});
