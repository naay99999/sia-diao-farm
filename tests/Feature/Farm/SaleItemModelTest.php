<?php

use App\Models\Grade;
use App\Models\Sale;
use App\Models\SaleItem;

test('a sale item belongs to a sale and a grade and stores a subtotal', function () {
    $sale = Sale::factory()->create();
    $grade = Grade::factory()->create(['name' => 'AB']);

    $item = SaleItem::factory()->for($sale)->for($grade)->create([
        'weight_kg' => 100,
        'price_per_kg' => 85.50,
        'subtotal' => 8550,
    ]);

    expect($item->sale->id)->toBe($sale->id);
    expect($item->grade->name)->toBe('AB');
    expect($item->weight_kg)->toBe('100.00');
    expect($item->price_per_kg)->toBe('85.50');
    expect($item->subtotal)->toBe('8550.00');
});
