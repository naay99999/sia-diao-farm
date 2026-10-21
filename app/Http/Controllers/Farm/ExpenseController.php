<?php

namespace App\Http\Controllers\Farm;

use App\Enums\ExpenseCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreExpenseRequest;
use App\Models\Expense;
use App\Models\Season;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = Expense::query()
            ->when($filters['from'] ?? null, fn ($q, string $from) => $q->whereDate('spent_on', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, string $to) => $q->whereDate('spent_on', '<=', $to));

        $expenses = (clone $query)
            ->with('season.orchard')
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->get();

        $totalsByCategory = $expenses
            ->groupBy(fn (Expense $expense) => $expense->category->value)
            ->map(fn ($group, string $category) => [
                'category' => $category,
                'label' => ExpenseCategory::from($category)->label(),
                'total' => (float) $group->sum('amount'),
            ])
            ->sortByDesc('total')
            ->values();

        return Inertia::render('farm/expenses/index', [
            'expenses' => $expenses,
            'total' => (float) $expenses->sum('amount'),
            'totalsByCategory' => $totalsByCategory,
            'filters' => ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null],
            'seasons' => Season::query()
                ->with('orchard')
                ->orderByDesc('started_on')
                ->get()
                ->map(fn (Season $season) => [
                    'id' => $season->id,
                    'label' => "{$season->orchard->name} · {$season->name}",
                ])
                ->values(),
            'categories' => collect(ExpenseCategory::cases())
                ->map(fn (ExpenseCategory $category) => ['value' => $category->value, 'label' => $category->label()])
                ->values(),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        Expense::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกค่าใช้จ่ายแล้ว']);

        return back();
    }

    public function update(StoreExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $expense->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกค่าใช้จ่ายแล้ว']);

        return back();
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบค่าใช้จ่ายแล้ว']);

        return back();
    }
}
