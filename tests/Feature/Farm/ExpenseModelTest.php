<?php

use App\Enums\ExpenseScope;
use App\Models\CropCycle;
use App\Models\Expense;

test('an expense with a crop cycle is a direct cost', function () {
    $cycle = CropCycle::factory()->create();
    $expense = Expense::factory()->for($cycle)->create(['amount' => 1500]);

    expect($expense->scope)->toBe(ExpenseScope::Direct);
    expect($expense->amount)->toBe('1500.00');
    expect($expense->cropCycle->id)->toBe($cycle->id);
});

test('an expense without a crop cycle is overhead', function () {
    $expense = Expense::factory()->overhead()->create();

    expect($expense->scope)->toBe(ExpenseScope::Overhead);
    expect($expense->crop_cycle_id)->toBeNull();
});

test('the direct and overhead query scopes filter correctly', function () {
    Expense::factory()->count(2)->create();
    Expense::factory()->overhead()->count(3)->create();

    expect(Expense::direct()->count())->toBe(2);
    expect(Expense::overhead()->count())->toBe(3);
});
