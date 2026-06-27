<?php

use App\Models\FruitType;
use App\Models\Grade;
use App\Models\User;

test('guests cannot view grades', function () {
    $this->get(route('grades.index'))->assertRedirect(route('login'));
});

test('a user can view the grades page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('grades.index'))
        ->assertOk();
});

test('a user can create a grade', function () {
    $type = FruitType::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('grades.store'), [
            'fruit_type_id' => $type->id,
            'name' => 'AB',
            'sort_order' => 1,
        ])
        ->assertRedirect(route('grades.index'));

    expect(Grade::where('name', 'AB')->exists())->toBeTrue();
});

test('grade validation requires fruit type and name', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('grades.index'))
        ->post(route('grades.store'), [
            'fruit_type_id' => 999,
            'name' => '',
        ])
        ->assertSessionHasErrors(['fruit_type_id', 'name']);
});

test('a user can update a grade', function () {
    $grade = Grade::factory()->create(['name' => 'C']);

    $this->actingAs(User::factory()->create())
        ->put(route('grades.update', $grade), [
            'fruit_type_id' => $grade->fruit_type_id,
            'name' => 'ตกไซซ์',
            'sort_order' => 5,
        ])
        ->assertRedirect(route('grades.index'));

    $grade->refresh();
    expect($grade->name)->toBe('ตกไซซ์');
    expect($grade->sort_order)->toBe(5);
});

test('a user can delete a grade', function () {
    $grade = Grade::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('grades.destroy', $grade))
        ->assertRedirect(route('grades.index'));

    expect(Grade::find($grade->id))->toBeNull();
});
