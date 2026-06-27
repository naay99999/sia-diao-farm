<?php

namespace Database\Factories;

use App\Models\FruitType;
use App\Models\FruitVariety;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FruitVariety>
 */
class FruitVarietyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fruit_type_id' => FruitType::factory(),
            'name' => fake()->randomElement(['หมอนทอง', 'ชะนี', 'น้ำดอกไม้', 'อกร่อง', 'พวงทอง']),
            'days_to_harvest' => fake()->numberBetween(90, 150),
        ];
    }
}
