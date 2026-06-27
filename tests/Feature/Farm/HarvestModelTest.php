<?php

use App\Models\CropCycle;
use App\Models\Harvest;
use App\Models\HarvestItem;

test('a harvest belongs to a crop cycle and has many items', function () {
    $cycle = CropCycle::factory()->create();
    $harvest = Harvest::factory()->for($cycle)->create();
    HarvestItem::factory()->count(2)->for($harvest)->create();

    expect($harvest->cropCycle->id)->toBe($cycle->id);
    expect($harvest->items)->toHaveCount(2);
});
