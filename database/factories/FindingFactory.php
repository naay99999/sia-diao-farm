<?php

namespace Database\Factories;

use App\Enums\FindingKind;
use App\Models\Finding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Finding>
 */
class FindingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'สิ่งที่พบ '.fake()->unique()->numerify('####'),
            'kind' => FindingKind::Problem,
        ];
    }

    public function problem(): static
    {
        return $this->state(fn () => ['kind' => FindingKind::Problem]);
    }

    public function stage(): static
    {
        return $this->state(fn () => ['kind' => FindingKind::Stage]);
    }
}
