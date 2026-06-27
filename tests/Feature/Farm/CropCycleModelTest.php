<?php

use App\Enums\CropCycleStage;
use App\Enums\CropCycleStatus;
use App\Models\CropCycle;
use App\Models\FruitVariety;
use App\Models\Plot;
use Illuminate\Support\Carbon;

test('recording flowering computes the expected harvest date', function () {
    $variety = FruitVariety::factory()->create(['days_to_harvest' => 120]);
    $plot = Plot::factory()->for($variety)->create();
    $cycle = CropCycle::factory()->for($plot)->for($variety)->create([
        'flowering_date' => null,
        'expected_harvest_date' => null,
        'stage' => CropCycleStage::Fruiting,
    ]);

    $cycle->recordFlowering(Carbon::parse('2026-01-01'));

    expect($cycle->flowering_date->toDateString())->toBe('2026-01-01');
    expect($cycle->expected_harvest_date->toDateString())->toBe('2026-05-01');
    expect($cycle->stage)->toBe(CropCycleStage::Flowering);
});

test('a cycle belongs to a plot and is castable', function () {
    $cycle = CropCycle::factory()->create([
        'status' => CropCycleStatus::Active,
    ]);

    expect($cycle->status)->toBe(CropCycleStatus::Active);
    expect($cycle->plot)->not->toBeNull();
});
