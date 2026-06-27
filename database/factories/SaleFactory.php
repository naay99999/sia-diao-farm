<?php

namespace Database\Factories;

use App\Models\CropCycle;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'crop_cycle_id' => CropCycle::factory(),
            'buyer_name' => fake()->randomElement(['ล้งเจ๊แดง', 'พ่อค้าคนกลาง', 'ตลาดไท', 'ล้งส่งออก']),
            'sold_on' => fake()->dateTimeBetween('-2 months', 'now'),
            'notes' => fake()->optional()->sentence(),
            'recorded_by' => User::factory(),
        ];
    }
}
