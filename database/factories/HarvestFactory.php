<?php

namespace Database\Factories;

use App\Models\Harvest;
use App\Models\Tree;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Harvest>
 */
class HarvestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tree_id' => Tree::factory(),
            'harvested_on' => now()->toDateString(),
            'weight_kg' => fake()->randomFloat(2, 5, 80),
            'fruit_count' => null,
            'notes' => null,
        ];
    }
}
