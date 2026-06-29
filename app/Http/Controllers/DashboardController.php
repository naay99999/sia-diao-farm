<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Harvest;
use App\Models\HarvestItem;
use App\Models\Plot;
use App\Models\Sale;
use App\Models\SaleItem;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $periods = $this->resolvePeriods();

        $stats = [];
        $plotProfits = [];
        $costPerKgByFruitType = [];
        $expensesByCategory = [];

        foreach ($periods as $period) {
            $stats[$period['key']] = $this->computeStats($period['ceYear']);
            $plotProfits[$period['key']] = $this->computePlotProfits($period['ceYear']);
            $costPerKgByFruitType[$period['key']] = $this->computeCostPerKgByFruitType($period['ceYear']);
            $expensesByCategory[$period['key']] = $this->computeExpensesByCategory($period['ceYear']);
        }

        return Inertia::render('dashboard', [
            'periods' => $periods,
            'stats' => $stats,
            'plotProfits' => $plotProfits,
            'costPerKgByFruitType' => $costPerKgByFruitType,
            'revenueVsExpensesByYear' => $this->computeRevenueVsExpensesByYear(),
            'expensesByCategory' => $expensesByCategory,
        ]);
    }

    /**
     * @return list<array{key: string, label: string, ceYear: int|null}>
     */
    private function resolvePeriods(): array
    {
        $ceYears = collect()
            ->merge(Sale::query()->pluck('sold_on')->map(fn ($date) => (int) substr((string) $date, 0, 4)))
            ->merge(Expense::query()->pluck('spent_on')->map(fn ($date) => (int) substr((string) $date, 0, 4)))
            ->merge(Harvest::query()->pluck('harvested_on')->map(fn ($date) => (int) substr((string) $date, 0, 4)))
            ->unique()
            ->sortDesc()
            ->values();

        $periods = $ceYears->map(fn (int $ceYear) => [
            'key' => (string) ($ceYear + 543),
            'label' => 'พ.ศ. '.($ceYear + 543),
            'ceYear' => $ceYear,
        ])->all();

        return [
            ['key' => 'all', 'label' => 'ทั้งหมด', 'ceYear' => null],
            ...$periods,
        ];
    }

    /**
     * @return array{totalYieldKg: float, totalRevenue: float, totalExpenses: float, netProfit: float, costPerKg: float}
     */
    private function computeStats(?int $ceYear): array
    {
        $totalRevenue = (float) SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->when($ceYear, fn ($q) => $q->whereYear('sales.sold_on', $ceYear))
            ->sum('sale_items.subtotal');

        $totalYieldKg = (float) HarvestItem::query()
            ->join('harvests', 'harvests.id', '=', 'harvest_items.harvest_id')
            ->when($ceYear, fn ($q) => $q->whereYear('harvests.harvested_on', $ceYear))
            ->sum('harvest_items.weight_kg');

        $totalExpenses = (float) Expense::query()
            ->when($ceYear, fn ($q) => $q->whereYear('spent_on', $ceYear))
            ->sum('amount');

        return [
            'totalYieldKg' => $totalYieldKg,
            'totalRevenue' => $totalRevenue,
            'totalExpenses' => $totalExpenses,
            'netProfit' => $totalRevenue - $totalExpenses,
            'costPerKg' => $totalYieldKg > 0 ? round($totalExpenses / $totalYieldKg, 2) : 0,
        ];
    }

    /**
     * @return list<array{plotName: string, revenue: float, directCosts: float, profit: float}>
     */
    private function computePlotProfits(?int $ceYear): array
    {
        $revenues = SaleItem::selectRaw('crop_cycles.plot_id, plots.name as plot_name, SUM(sale_items.subtotal) as revenue')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('crop_cycles', 'crop_cycles.id', '=', 'sales.crop_cycle_id')
            ->join('plots', 'plots.id', '=', 'crop_cycles.plot_id')
            ->when($ceYear, fn ($q) => $q->whereYear('sales.sold_on', $ceYear))
            ->groupBy('crop_cycles.plot_id', 'plots.name')
            ->get()
            ->keyBy('plot_id');

        $costs = Expense::selectRaw('crop_cycles.plot_id, SUM(expenses.amount) as total_cost')
            ->join('crop_cycles', 'crop_cycles.id', '=', 'expenses.crop_cycle_id')
            ->whereNotNull('expenses.crop_cycle_id')
            ->when($ceYear, fn ($q) => $q->whereYear('expenses.spent_on', $ceYear))
            ->groupBy('crop_cycles.plot_id')
            ->get()
            ->keyBy('plot_id');

        return array_values($revenues->keys()
            ->merge($costs->keys())
            ->unique()
            ->map(function ($plotId) use ($revenues, $costs) {
                $rev = (float) ($revenues->get($plotId)?->getAttribute('revenue') ?? 0);
                $cost = (float) ($costs->get($plotId)?->getAttribute('total_cost') ?? 0);
                $foundPlot = Plot::find($plotId);
                $plotName = $revenues->get($plotId)?->getAttribute('plot_name')
                    ?? ($foundPlot instanceof Plot ? $foundPlot->name : null)
                    ?? 'ไม่ทราบชื่อ';

                return [
                    'plotName' => (string) $plotName,
                    'revenue' => $rev,
                    'directCosts' => $cost,
                    'profit' => $rev - $cost,
                ];
            })
            ->sortByDesc('profit')
            ->values()
            ->all());
    }

    /**
     * @return list<array{fruitTypeName: string, totalCost: float, totalYieldKg: float, costPerKg: float}>
     */
    private function computeCostPerKgByFruitType(?int $ceYear): array
    {
        $costs = Expense::selectRaw('fruit_types.id, fruit_types.name as fruit_type_name, SUM(expenses.amount) as total_cost')
            ->join('crop_cycles', 'crop_cycles.id', '=', 'expenses.crop_cycle_id')
            ->join('fruit_varieties', 'fruit_varieties.id', '=', 'crop_cycles.fruit_variety_id')
            ->join('fruit_types', 'fruit_types.id', '=', 'fruit_varieties.fruit_type_id')
            ->whereNotNull('expenses.crop_cycle_id')
            ->when($ceYear, fn ($q) => $q->whereYear('expenses.spent_on', $ceYear))
            ->groupBy('fruit_types.id', 'fruit_types.name')
            ->get()
            ->keyBy('id');

        $yields = HarvestItem::selectRaw('fruit_types.id, SUM(harvest_items.weight_kg) as total_weight')
            ->join('harvests', 'harvests.id', '=', 'harvest_items.harvest_id')
            ->join('crop_cycles', 'crop_cycles.id', '=', 'harvests.crop_cycle_id')
            ->join('fruit_varieties', 'fruit_varieties.id', '=', 'crop_cycles.fruit_variety_id')
            ->join('fruit_types', 'fruit_types.id', '=', 'fruit_varieties.fruit_type_id')
            ->when($ceYear, fn ($q) => $q->whereYear('harvests.harvested_on', $ceYear))
            ->groupBy('fruit_types.id')
            ->get()
            ->keyBy('id');

        return array_values($costs->keys()
            ->merge($yields->keys())
            ->unique()
            ->map(function ($id) use ($costs, $yields) {
                $cost = (float) ($costs->get($id)?->getAttribute('total_cost') ?? 0);
                $weight = (float) ($yields->get($id)?->getAttribute('total_weight') ?? 0);

                return [
                    'fruitTypeName' => (string) ($costs->get($id)?->getAttribute('fruit_type_name') ?? ''),
                    'totalCost' => $cost,
                    'totalYieldKg' => $weight,
                    'costPerKg' => $weight > 0 ? round($cost / $weight, 2) : 0.0,
                ];
            })
            ->values()
            ->all());
    }

    /**
     * Groups in PHP instead of SQL to stay SQLite-safe.
     *
     * @return list<array{beYear: int, revenue: float, expenses: float}>
     */
    private function computeRevenueVsExpensesByYear(): array
    {
        $saleItems = SaleItem::select('sale_items.subtotal', 'sales.sold_on')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->get();

        $expenses = Expense::select('amount', 'spent_on')->get();

        $revenueByYear = $saleItems
            ->groupBy(fn ($si) => (int) substr((string) $si->getAttribute('sold_on'), 0, 4))
            ->map(fn ($items) => $items->sum(fn ($i) => (float) $i->subtotal));

        $expenseByYear = $expenses
            ->groupBy(fn ($e) => (int) substr((string) $e->spent_on, 0, 4))
            ->map(fn ($items) => $items->sum(fn ($i) => (float) $i->amount));

        return array_values($revenueByYear->keys()
            ->merge($expenseByYear->keys())
            ->unique()
            ->sort()
            ->map(fn ($y) => [
                'beYear' => $y + 543,
                'revenue' => (float) ($revenueByYear[$y] ?? 0),
                'expenses' => (float) ($expenseByYear[$y] ?? 0),
            ])
            ->values()
            ->all());
    }

    /**
     * @return list<array{categoryName: string, total: float}>
     */
    private function computeExpensesByCategory(?int $ceYear): array
    {
        return Expense::selectRaw('expense_categories.name as category_name, SUM(expenses.amount) as total')
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->when($ceYear, fn ($q) => $q->whereYear('expenses.spent_on', $ceYear))
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'categoryName' => (string) $row->getAttribute('category_name'),
                'total' => (float) $row->getAttribute('total'),
            ])
            ->pipe(fn ($c) => array_values($c->all()));
    }
}
