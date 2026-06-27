<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\Harvest;
use App\Models\HarvestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HarvestItem>
 */
class HarvestItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'harvest_id' => Harvest::factory(),
            'grade_id' => Grade::factory(),
            'weight_kg' => fake()->randomFloat(2, 10, 500),
        ];
    }
}
