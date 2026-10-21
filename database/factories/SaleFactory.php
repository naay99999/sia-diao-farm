<?php

namespace Database\Factories;

use App\Models\Orchard;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'orchard_id' => Orchard::factory(),
            'sold_on' => now()->toDateString(),
            'buyer_name' => null,
            'weight_kg' => fake()->randomFloat(2, 50, 500),
            'amount' => fake()->randomFloat(2, 5000, 90000),
            'notes' => null,
        ];
    }
}
