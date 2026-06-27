<?php

use App\Models\FruitType;
use App\Models\FruitVariety;

test('a fruit type has many varieties', function () {
    $type = FruitType::factory()->create();
    FruitVariety::factory()->count(2)->for($type)->create();

    expect($type->varieties)->toHaveCount(2);
});
