<?php

use App\Enums\ExpenseScope;
use App\Models\ExpenseCategory;
use App\Models\User;

test('guests cannot view expense categories', function () {
    $this->get(route('expense-categories.index'))->assertRedirect(route('login'));
});

test('a user can view the expense categories page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('expense-categories.index'))
        ->assertOk();
});

test('a user can create an expense category', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('expense-categories.store'), [
            'name' => 'ค่าปุ๋ย',
            'default_scope' => ExpenseScope::Direct->value,
        ])
        ->assertRedirect(route('expense-categories.index'));

    expect(ExpenseCategory::where('name', 'ค่าปุ๋ย')->exists())->toBeTrue();
});

test('expense category validation requires name and a valid scope', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('expense-categories.index'))
        ->post(route('expense-categories.store'), [
            'name' => '',
            'default_scope' => 'invalid',
        ])
        ->assertSessionHasErrors(['name', 'default_scope']);
});

test('a user can update an expense category', function () {
    $category = ExpenseCategory::factory()->create([
        'name' => 'ค่ายา',
        'default_scope' => ExpenseScope::Direct,
    ]);

    $this->actingAs(User::factory()->create())
        ->put(route('expense-categories.update', $category), [
            'name' => 'ค่ายาฆ่าแมลง',
            'default_scope' => ExpenseScope::Overhead->value,
        ])
        ->assertRedirect(route('expense-categories.index'));

    $category->refresh();
    expect($category->name)->toBe('ค่ายาฆ่าแมลง');
    expect($category->default_scope)->toBe(ExpenseScope::Overhead);
});

test('a user can delete an expense category', function () {
    $category = ExpenseCategory::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('expense-categories.destroy', $category))
        ->assertRedirect(route('expense-categories.index'));

    expect(ExpenseCategory::find($category->id))->toBeNull();
});
