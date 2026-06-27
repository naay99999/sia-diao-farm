<?php

use App\Models\Activity;
use App\Models\ActivityType;

test('an activity type has many activities', function () {
    $type = ActivityType::factory()->create();
    Activity::factory()->count(2)->for($type)->create();

    expect($type->activities)->toHaveCount(2);
});
