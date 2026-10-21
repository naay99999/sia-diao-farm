<?php

namespace Database\Factories;

use App\Enums\CareType;
use App\Models\CareRecord;
use App\Models\Orchard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CareRecord>
 */
class CareRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'orchard_id' => Orchard::factory(),
            'type' => CareType::Fertilizer,
            'performed_on' => now()->toDateString(),
            'product' => null,
            'quantity' => null,
            'notes' => null,
        ];
    }
}
