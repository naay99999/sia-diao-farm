<?php

namespace Database\Factories;

use App\Models\FruitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FruitType>
 */
class FruitTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['ทุเรียน', 'มะม่วง', 'มังคุด', 'ลำไย', 'เงาะ']),
        ];
    }
}
