<?php

use App\Enums\CropCycleStatus;
use App\Models\CropCycle;
use App\Models\FruitVariety;
use App\Models\Plot;

test('a plot belongs to a fruit variety', function () {
    $variety = FruitVariety::factory()->create();
    $plot = Plot::factory()->for($variety)->create();

    expect($plot->fruitVariety->id)->toBe($variety->id);
});

test('tree age in years is computed from planted_at', function () {
    $plot = Plot::factory()->create([
        'planted_at' => now()->subYears(5)->startOfDay(),
    ]);

    expect($plot->tree_age_years)->toBe(5);
});

test('tree age is null when planted_at is missing', function () {
    $plot = Plot::factory()->create(['planted_at' => null]);

    expect($plot->tree_age_years)->toBeNull();
});

test('active crop cycle returns the active cycle not a newer closed one', function () {
    $plot = Plot::factory()->create();
    $active = CropCycle::factory()->for($plot)->create(['status' => CropCycleStatus::Active]);
    CropCycle::factory()->for($plot)->create(['status' => CropCycleStatus::Closed]);

    expect($plot->activeCropCycle->id)->toBe($active->id);
});
