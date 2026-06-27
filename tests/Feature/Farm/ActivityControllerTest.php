<?php

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\CropCycle;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;

test('guests cannot record an activity', function () {
    $cycle = CropCycle::factory()->create();

    $this->post(route('crop-cycles.activities.store', $cycle), [])
        ->assertRedirect(route('login'));
});

test('a user can record an activity for a crop cycle', function () {
    $cycle = CropCycle::factory()->create();
    $type = ActivityType::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('crop-cycles.activities.store', $cycle), [
            'activity_type_id' => $type->id,
            'performed_on' => '2026-02-01',
            'notes' => 'ปุ๋ยสูตร 15-15-15',
        ])
        ->assertRedirect(route('crop-cycles.show', $cycle));

    $activity = Activity::first();
    expect($activity->crop_cycle_id)->toBe($cycle->id);
    expect($activity->activity_type_id)->toBe($type->id);
    expect($activity->recorded_by)->toBe($user->id);
});

test('recording an activity requires a type and date', function () {
    $cycle = CropCycle::factory()->create();

    $this->actingAs(User::factory()->create())
        ->from(route('crop-cycles.show', $cycle))
        ->post(route('crop-cycles.activities.store', $cycle), [
            'activity_type_id' => 999,
            'performed_on' => '',
        ])
        ->assertSessionHasErrors(['activity_type_id', 'performed_on']);
});

test('recording an activity with a cost creates a linked direct expense', function () {
    $cycle = CropCycle::factory()->create();
    $type = ActivityType::factory()->create();
    $category = ExpenseCategory::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('crop-cycles.activities.store', $cycle), [
            'activity_type_id' => $type->id,
            'performed_on' => '2026-02-01',
            'cost' => 1200,
            'expense_category_id' => $category->id,
        ])
        ->assertRedirect(route('crop-cycles.show', $cycle));

    $activity = Activity::first();
    $expense = Expense::first();
    expect($expense)->not->toBeNull();
    expect($expense->activity_id)->toBe($activity->id);
    expect($expense->crop_cycle_id)->toBe($cycle->id);
    expect($expense->amount)->toBe('1200.00');
    expect($expense->expense_category_id)->toBe($category->id);
});

test('a cost requires an expense category', function () {
    $cycle = CropCycle::factory()->create();
    $type = ActivityType::factory()->create();

    $this->actingAs(User::factory()->create())
        ->from(route('crop-cycles.show', $cycle))
        ->post(route('crop-cycles.activities.store', $cycle), [
            'activity_type_id' => $type->id,
            'performed_on' => '2026-02-01',
            'cost' => 1200,
        ])
        ->assertSessionHasErrors('expense_category_id');
});

test('a user can delete an activity', function () {
    $activity = Activity::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('activities.destroy', $activity))
        ->assertRedirect();

    expect(Activity::find($activity->id))->toBeNull();
});
