<?php

use App\Models\CropCycle;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;

test('guests cannot view the overhead expenses page', function () {
    $this->get(route('expenses.index'))->assertRedirect(route('login'));
});

test('a user can view the overhead expenses page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('expenses.index'))
        ->assertOk();
});

test('a user can record an overhead expense', function () {
    $category = ExpenseCategory::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('expenses.store'), [
            'expense_category_id' => $category->id,
            'amount' => 800,
            'spent_on' => '2026-02-10',
            'description' => 'ค่าน้ำมันรถตัดหญ้า',
        ])
        ->assertRedirect(route('expenses.index'));

    $expense = Expense::first();
    expect($expense->crop_cycle_id)->toBeNull();
    expect($expense->amount)->toBe('800.00');
    expect($expense->recorded_by)->toBe($user->id);
});

test('recording an overhead expense requires category, amount and date', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('expenses.index'))
        ->post(route('expenses.store'), [
            'expense_category_id' => 999,
            'amount' => 0,
            'spent_on' => '',
        ])
        ->assertSessionHasErrors(['expense_category_id', 'amount', 'spent_on']);
});

test('a user can record a direct expense for a crop cycle', function () {
    $cycle = CropCycle::factory()->create();
    $category = ExpenseCategory::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('crop-cycles.expenses.store', $cycle), [
            'expense_category_id' => $category->id,
            'amount' => 2500,
            'spent_on' => '2026-02-12',
            'description' => 'ค่าแรงเก็บผลผลิต',
        ])
        ->assertRedirect(route('crop-cycles.show', $cycle));

    $expense = Expense::first();
    expect($expense->crop_cycle_id)->toBe($cycle->id);
    expect($expense->amount)->toBe('2500.00');
});

test('a user can delete an expense', function () {
    $expense = Expense::factory()->overhead()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('expenses.destroy', $expense))
        ->assertRedirect();

    expect(Expense::find($expense->id))->toBeNull();
});
