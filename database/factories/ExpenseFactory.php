<?php

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => null,
            'category' => fake()->randomElement(ExpenseCategory::cases()),
            'spent_on' => now()->toDateString(),
            'amount' => fake()->randomFloat(2, 100, 20000),
            'notes' => null,
        ];
    }
}
