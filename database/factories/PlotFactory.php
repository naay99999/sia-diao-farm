<?php

namespace Database\Factories;

use App\Models\FruitVariety;
use App\Models\Plot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plot>
 */
class PlotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'แปลง'.fake()->randomElement(['ทุเรียนทิศเหนือ', 'มะม่วงหน้าบ้าน', 'ทิศใต้', 'ริมคลอง']),
            'fruit_variety_id' => FruitVariety::factory(),
            'tree_count' => fake()->numberBetween(20, 200),
            'planted_at' => fake()->dateTimeBetween('-10 years', '-1 year'),
            'area_rai' => fake()->randomFloat(2, 1, 20),
            'notes' => null,
        ];
    }
}
