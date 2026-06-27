<?php

use App\Models\Grade;
use App\Models\Harvest;
use App\Models\HarvestItem;

test('a harvest item belongs to a harvest and a grade with a weight', function () {
    $harvest = Harvest::factory()->create();
    $grade = Grade::factory()->create(['name' => 'AB']);

    $item = HarvestItem::factory()->for($harvest)->for($grade)->create(['weight_kg' => 120.5]);

    expect($item->harvest->id)->toBe($harvest->id);
    expect($item->grade->name)->toBe('AB');
    expect($item->weight_kg)->toBe('120.50');
});
