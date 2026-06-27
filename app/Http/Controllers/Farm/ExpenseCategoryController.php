<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreExpenseCategoryRequest;
use App\Http\Requests\Farm\UpdateExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('farm/expense-categories/index', [
            'expenseCategories' => ExpenseCategory::orderBy('name')->get(),
        ]);
    }

    public function store(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        ExpenseCategory::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'เพิ่มหมวดค่าใช้จ่ายแล้ว']);

        return to_route('expense-categories.index');
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $expenseCategory->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'อัปเดตหมวดค่าใช้จ่ายแล้ว']);

        return to_route('expense-categories.index');
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        $expenseCategory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบหมวดค่าใช้จ่ายแล้ว']);

        return to_route('expense-categories.index');
    }
}
