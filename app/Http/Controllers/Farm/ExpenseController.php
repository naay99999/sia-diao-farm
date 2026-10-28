<?php

namespace App\Http\Controllers\Farm;

use App\Enums\ExpenseCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\RecordFilterRequest;
use App\Http\Requests\Farm\StoreExpenseRequest;
use App\Models\Expense;
use App\Support\RecordFilters;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

class ExpenseController extends Controller
{
    public function index(RecordFilterRequest $request): Response|RedirectResponse
    {
        $filters = $request->filters();

        $query = RecordFilters::apply(Expense::query(), $filters, 'spent_on', null, 'season');

        $expenses = (clone $query)
            ->with(['season.orchard', 'creator:id,name', 'updater:id,name'])
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->paginate(RecordController::PER_PAGE);

        if ($expenses->total() > 0 && $expenses->currentPage() > $expenses->lastPage()) {
            return to_route('expenses.index', [...$request->query(), 'page' => $expenses->lastPage()]);
        }

        $totalsByCategory = (clone $query)
            ->toBase()
            ->groupBy('category')
            ->selectRaw('category, sum(amount) as total')
            ->get()
            ->map(fn (stdClass $row): array => [
                'category' => $row->category,
                'label' => ExpenseCategory::from($row->category)->label(),
                'total' => (float) $row->total,
            ])
            ->sortByDesc('total')
            ->values();

        return Inertia::render('records/expenses', [
            'expenses' => $expenses
                ->through(fn (Expense $expense): array => [
                    ...$expense->makeHidden(['creator', 'updater'])->toArray(),
                    'fromAi' => $expense->isFromAi(),
                    'aiBy' => $expense->aiActorName(),
                    'createdBy' => $expense->creator?->name,
                ])
                ->withQueryString(),
            'total' => (float) $totalsByCategory->sum('total'),
            'totalsByCategory' => $totalsByCategory,
            'filters' => $filters,
            ...RecordFilters::options(),
            'categories' => fn () => collect(ExpenseCategory::cases())
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
