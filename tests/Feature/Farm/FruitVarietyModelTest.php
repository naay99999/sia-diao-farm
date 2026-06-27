<?php

use App\Models\FruitType;
use App\Models\FruitVariety;

test('a variety belongs to a fruit type', function () {
    $type = FruitType::factory()->create(['name' => 'ทุเรียน']);
    $variety = FruitVariety::factory()->for($type)->create([
        'name' => 'หมอนทอง',
        'days_to_harvest' => 135,
    ]);

    expect($variety->fruitType->name)->toBe('ทุเรียน');
    expect($variety->days_to_harvest)->toBe(135);
});
