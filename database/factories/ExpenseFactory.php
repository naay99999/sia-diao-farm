<?php

namespace Database\Factories;

use App\Models\CropCycle;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'expense_category_id' => ExpenseCategory::factory(),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'spent_on' => fake()->dateTimeBetween('-3 months', 'now'),
            'description' => fake()->optional()->sentence(),
            'crop_cycle_id' => CropCycle::factory(),
            'activity_id' => null,
            'recorded_by' => User::factory(),
        ];
    }

    public function overhead(): static
    {
        return $this->state(fn (array $attributes) => [
            'crop_cycle_id' => null,
        ]);
    }
}
