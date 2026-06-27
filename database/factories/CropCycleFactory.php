<?php

namespace Database\Factories;

use App\Enums\CropCycleStage;
use App\Enums\CropCycleStatus;
use App\Models\CropCycle;
use App\Models\FruitVariety;
use App\Models\Plot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CropCycle>
 */
class CropCycleFactory extends Factory
{
    public function definition(): array
    {
        $variety = FruitVariety::factory();

        return [
            'plot_id' => Plot::factory(),
            'fruit_variety_id' => $variety,
            'label' => 'รอบ '.fake()->numberBetween(2566, 2570),
            'stage' => CropCycleStage::SoilPrep,
            'status' => CropCycleStatus::Active,
            'flowering_date' => null,
            'expected_harvest_date' => null,
            'started_at' => now()->subMonths(2),
            'closed_at' => null,
            'recorded_by' => User::factory(),
            'notes' => null,
        ];
    }
}
