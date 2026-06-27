<?php

use App\Models\FruitType;
use App\Models\Grade;

test('a grade belongs to a fruit type', function () {
    $type = FruitType::factory()->create(['name' => 'ทุเรียน']);
    $grade = Grade::factory()->for($type)->create(['name' => 'AB', 'sort_order' => 1]);

    expect($grade->fruitType->name)->toBe('ทุเรียน');
    expect($grade->sort_order)->toBe(1);
});

test('a fruit type has many grades', function () {
    $type = FruitType::factory()->create();
    Grade::factory()->count(3)->for($type)->create();

    expect($type->grades)->toHaveCount(3);
});
