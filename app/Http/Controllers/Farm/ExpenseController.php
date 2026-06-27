<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreExpenseRequest;
use App\Models\CropCycle;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('farm/expenses/index', [
            'expenses' => Expense::overhead()
                ->with('expenseCategory')
                ->latest('spent_on')
                ->get(),
            'expenseCategories' => ExpenseCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        Expense::create([
            ...$request->validated(),
            'crop_cycle_id' => null,
            'recorded_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกค่าใช้จ่ายส่วนกลางแล้ว']);

        return to_route('expenses.index');
    }

    public function storeForCycle(StoreExpenseRequest $request, CropCycle $cropCycle): RedirectResponse
    {
        $cropCycle->expenses()->create([
            ...$request->validated(),
            'recorded_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกค่าใช้จ่ายแล้ว']);

        return to_route('crop-cycles.show', $cropCycle);
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $cropCycleId = $expense->crop_cycle_id;
        $expense->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบค่าใช้จ่ายแล้ว']);

        if ($cropCycleId !== null) {
            return to_route('crop-cycles.show', $cropCycleId);
        }

        return to_route('expenses.index');
    }
}
