<?php

use App\Enums\CropCycleStage;
use App\Models\CropCycle;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FruitVariety;
use App\Models\Grade;
use App\Models\Harvest;
use App\Models\HarvestItem;
use App\Models\Plot;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;

test('guests cannot create a crop cycle', function () {
    $plot = Plot::factory()->create();

    $this->post(route('plots.crop-cycles.store', $plot), [])
        ->assertRedirect(route('login'));
});

test('guests cannot update a crop cycle', function () {
    $cycle = CropCycle::factory()->create();

    $this->patch(route('crop-cycles.update', $cycle), [])
        ->assertRedirect(route('login'));
});

test('a user can create a crop cycle for a plot', function () {
    $variety = FruitVariety::factory()->create();
    $plot = Plot::factory()->for($variety)->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('plots.crop-cycles.store', $plot), [
            'label' => 'รอบ 2569',
            'started_at' => '2026-01-01',
        ])
        ->assertRedirect(route('plots.show', $plot));

    $cycle = CropCycle::first();
    expect($cycle->plot_id)->toBe($plot->id);
    expect($cycle->fruit_variety_id)->toBe($variety->id);
    expect($cycle->recorded_by)->toBe($user->id);
});

test('creating a cycle requires a label and start date', function () {
    $plot = Plot::factory()->create();

    $this->actingAs(User::factory()->create())
        ->from(route('plots.show', $plot))
        ->post(route('plots.crop-cycles.store', $plot), [
            'label' => '',
            'started_at' => '',
        ])
        ->assertSessionHasErrors(['label', 'started_at']);
});

test('recording flowering forecasts the harvest date', function () {
    $variety = FruitVariety::factory()->create(['days_to_harvest' => 120]);
    $plot = Plot::factory()->for($variety)->create();
    $cycle = CropCycle::factory()->for($plot)->for($variety)->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('crop-cycles.update', $cycle), [
            'flowering_date' => '2026-01-01',
        ])
        ->assertRedirect(route('plots.show', $plot));

    $cycle->refresh();
    expect($cycle->flowering_date->toDateString())->toBe('2026-01-01');
    expect($cycle->expected_harvest_date->toDateString())->toBe('2026-05-01');
    expect($cycle->stage)->toBe(CropCycleStage::Flowering);
});

test('a user can update the stage of a cycle', function () {
    $cycle = CropCycle::factory()->create(['stage' => CropCycleStage::SoilPrep]);

    $this->actingAs(User::factory()->create())
        ->patch(route('crop-cycles.update', $cycle), [
            'stage' => CropCycleStage::Fruiting->value,
        ])
        ->assertRedirect(route('plots.show', $cycle->plot_id));

    expect($cycle->refresh()->stage)->toBe(CropCycleStage::Fruiting);
});

test('guests cannot view a crop cycle', function () {
    $cycle = CropCycle::factory()->create();

    $this->get(route('crop-cycles.show', $cycle))->assertRedirect(route('login'));
});

test('a user can view a crop cycle detail page', function () {
    $cycle = CropCycle::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('crop-cycles.show', $cycle))
        ->assertOk();
});

test('the crop cycle page exposes yield, revenue, cost-per-kg and profit', function () {
    $cycle = CropCycle::factory()->create();

    $harvest = Harvest::factory()->for($cycle)->create();
    $grade = Grade::factory()->create();
    HarvestItem::factory()->for($harvest)->for($grade)->create(['weight_kg' => 200]);

    $sale = Sale::factory()->for($cycle)->create();
    SaleItem::factory()->for($sale)->for($grade)->create([
        'weight_kg' => 200,
        'price_per_kg' => 50,
        'subtotal' => 10000,
    ]);

    Expense::factory()->for($cycle)->create([
        'expense_category_id' => ExpenseCategory::factory(),
        'amount' => 4000,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('crop-cycles.show', $cycle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('totalYield', fn ($value) => (float) $value === 200.0)
            ->where('revenue', fn ($value) => (float) $value === 10000.0)
            ->where('totalDirectCost', fn ($value) => (float) $value === 4000.0)
            ->where('costPerKg', fn ($value) => (float) $value === 20.0)
            ->where('profit', fn ($value) => (float) $value === 6000.0)
        );
});
