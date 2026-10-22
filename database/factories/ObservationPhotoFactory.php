<?php

namespace Database\Factories;

use App\Models\Observation;
use App\Models\ObservationPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ObservationPhoto>
 */
class ObservationPhotoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'observation_id' => Observation::factory(),
            'path' => 'observations/'.fake()->uuid().'.jpg',
        ];
    }
}
