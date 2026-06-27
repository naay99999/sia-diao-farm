<?php

use App\Enums\ExpenseScope;
use App\Models\Expense;
use App\Models\ExpenseCategory;

test('an expense category casts its default scope and has many expenses', function () {
    $category = ExpenseCategory::factory()->create([
        'default_scope' => ExpenseScope::Direct,
    ]);
    Expense::factory()->count(2)->for($category)->create();

    expect($category->default_scope)->toBe(ExpenseScope::Direct);
    expect($category->expenses)->toHaveCount(2);
});
