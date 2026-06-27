<?php

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\CropCycle;

test('an activity belongs to a crop cycle and an activity type', function () {
    $cycle = CropCycle::factory()->create();
    $type = ActivityType::factory()->create(['name' => 'ใส่ปุ๋ย']);

    $activity = Activity::factory()->for($cycle)->for($type)->create([
        'notes' => 'ปุ๋ยสูตร 15-15-15 2 กระสอบ',
    ]);

    expect($activity->cropCycle->id)->toBe($cycle->id);
    expect($activity->activityType->name)->toBe('ใส่ปุ๋ย');
    expect($activity->notes)->toBe('ปุ๋ยสูตร 15-15-15 2 กระสอบ');
});
