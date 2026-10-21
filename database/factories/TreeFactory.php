<?php

namespace Database\Factories;

use App\Models\Orchard;
use App\Models\Tree;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tree>
 */
class TreeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'orchard_id' => Orchard::factory(),
            'code' => 'T-'.fake()->unique()->numerify('####'),
            'variety' => null,
            'planted_on' => null,
            'removed_on' => null,
            'notes' => null,
        ];
    }

    public function removed(): static
    {
        return $this->state(fn () => ['removed_on' => now()->toDateString()]);
    }
}
