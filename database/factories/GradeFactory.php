<?php

namespace Database\Factories;

use App\Models\FruitType;
use App\Models\Grade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fruit_type_id' => FruitType::factory(),
            'name' => fake()->randomElement(['AB', 'C', 'ตกไซซ์', 'คละ']),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
