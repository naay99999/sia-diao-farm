<?php

namespace Database\Factories;

use App\Models\ActivityType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityType>
 */
class ActivityTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['ใส่ปุ๋ย', 'พ่นยา', 'รดน้ำ', 'ตัดแต่งกิ่ง', 'กำจัดวัชพืช']),
        ];
    }
}
