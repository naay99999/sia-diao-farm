<?php

namespace Database\Factories;

use App\Models\Orchard;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'orchard_id' => Orchard::factory(),
            'name' => 'ฤดู '.(now()->year + 543),
            'started_on' => now()->startOfYear()->toDateString(),
            'ended_on' => null,
            'notes' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_on' => now()->toDateString(),
        ]);
    }
}
