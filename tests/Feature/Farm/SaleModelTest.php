<?php

use App\Models\CropCycle;
use App\Models\Sale;
use App\Models\SaleItem;

test('a sale belongs to a crop cycle and has many items', function () {
    $cycle = CropCycle::factory()->create();
    $sale = Sale::factory()->for($cycle)->create(['buyer_name' => 'ล้งเจ๊แดง']);
    SaleItem::factory()->count(2)->for($sale)->create();

    expect($sale->cropCycle->id)->toBe($cycle->id);
    expect($sale->buyer_name)->toBe('ล้งเจ๊แดง');
    expect($sale->items)->toHaveCount(2);
});
