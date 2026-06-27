<?php

namespace Database\Factories;

use App\Models\CropCycle;
use App\Models\Harvest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Harvest>
 */
class HarvestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'crop_cycle_id' => CropCycle::factory(),
            'harvested_on' => fake()->dateTimeBetween('-2 months', 'now'),
            'notes' => fake()->optional()->sentence(),
            'recorded_by' => User::factory(),
        ];
    }
}
