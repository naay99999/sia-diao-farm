<?php

use App\Models\CropCycle;
use App\Models\Grade;
use App\Models\Harvest;
use App\Models\User;

test('guests cannot record a harvest', function () {
    $cycle = CropCycle::factory()->create();

    $this->post(route('crop-cycles.harvests.store', $cycle), [])
        ->assertRedirect(route('login'));
});

test('a user can record a harvest with graded items', function () {
    $cycle = CropCycle::factory()->create();
    $gradeA = Grade::factory()->create();
    $gradeB = Grade::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('crop-cycles.harvests.store', $cycle), [
            'harvested_on' => '2026-05-01',
            'notes' => 'เก็บรอบแรก',
            'items' => [
                ['grade_id' => $gradeA->id, 'weight_kg' => 120.5],
                ['grade_id' => $gradeB->id, 'weight_kg' => 80],
            ],
        ])
        ->assertRedirect(route('crop-cycles.show', $cycle));

    $harvest = Harvest::first();
    expect($harvest->crop_cycle_id)->toBe($cycle->id);
    expect($harvest->recorded_by)->toBe($user->id);
    expect($harvest->items)->toHaveCount(2);
    expect($harvest->items->pluck('weight_kg')->all())->toEqual(['120.50', '80.00']);
});

test('a harvest requires a date and at least one item', function () {
    $cycle = CropCycle::factory()->create();

    $this->actingAs(User::factory()->create())
        ->from(route('crop-cycles.show', $cycle))
        ->post(route('crop-cycles.harvests.store', $cycle), [
            'harvested_on' => '',
            'items' => [],
        ])
        ->assertSessionHasErrors(['harvested_on', 'items']);
});

test('harvest items require a valid grade and a positive weight', function () {
    $cycle = CropCycle::factory()->create();

    $this->actingAs(User::factory()->create())
        ->from(route('crop-cycles.show', $cycle))
        ->post(route('crop-cycles.harvests.store', $cycle), [
            'harvested_on' => '2026-05-01',
            'items' => [
                ['grade_id' => 999, 'weight_kg' => 0],
            ],
        ])
        ->assertSessionHasErrors(['items.0.grade_id', 'items.0.weight_kg']);
});

test('a user can delete a harvest', function () {
    $harvest = Harvest::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('harvests.destroy', $harvest))
        ->assertRedirect();

    expect(Harvest::find($harvest->id))->toBeNull();
});
