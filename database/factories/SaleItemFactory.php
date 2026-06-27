<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    public function definition(): array
    {
        $weight = fake()->randomFloat(2, 10, 500);
        $price = fake()->randomFloat(2, 20, 150);

        return [
            'sale_id' => Sale::factory(),
            'grade_id' => Grade::factory(),
            'weight_kg' => $weight,
            'price_per_kg' => $price,
            'subtotal' => round($weight * $price, 2),
        ];
    }
}
