<?php

namespace Database\Factories;

use App\Models\Orchard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Orchard>
 */
class OrchardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'สวน '.fake()->word(),
            'location' => null,
            'notes' => null,
        ];
    }
}
