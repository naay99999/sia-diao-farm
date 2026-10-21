<?php

namespace Database\Factories;

use App\Models\FarmNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FarmNote>
 */
class FarmNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'body' => 'โน้ต '.fake()->unique()->numerify('####'),
            'finding_id' => null,
            'tree_id' => null,
        ];
    }
}
