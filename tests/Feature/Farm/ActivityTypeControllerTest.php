<?php

use App\Models\ActivityType;
use App\Models\User;

test('guests cannot view activity types', function () {
    $this->get(route('activity-types.index'))->assertRedirect(route('login'));
});

test('a user can view the activity types page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('activity-types.index'))
        ->assertOk();
});

test('a user can create an activity type', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('activity-types.store'), ['name' => 'ใส่ปุ๋ย'])
        ->assertRedirect(route('activity-types.index'));

    expect(ActivityType::where('name', 'ใส่ปุ๋ย')->exists())->toBeTrue();
});

test('an activity type name is required', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('activity-types.index'))
        ->post(route('activity-types.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('a user can update an activity type', function () {
    $type = ActivityType::factory()->create(['name' => 'รดน้ำ']);

    $this->actingAs(User::factory()->create())
        ->put(route('activity-types.update', $type), ['name' => 'รดน้ำเช้า'])
        ->assertRedirect(route('activity-types.index'));

    expect($type->refresh()->name)->toBe('รดน้ำเช้า');
});

test('a user can delete an activity type', function () {
    $type = ActivityType::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('activity-types.destroy', $type))
        ->assertRedirect(route('activity-types.index'));

    expect(ActivityType::find($type->id))->toBeNull();
});
