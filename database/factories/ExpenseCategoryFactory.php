<?php

namespace Database\Factories;

use App\Enums\ExpenseScope;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['ค่าปุ๋ย', 'ค่ายา', 'ค่าแรง', 'ค่าน้ำมัน', 'ค่าซ่อม']),
            'default_scope' => fake()->randomElement(ExpenseScope::cases()),
        ];
    }
}
