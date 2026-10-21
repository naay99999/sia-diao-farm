<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreSeasonRequest;
use App\Models\Harvest;
use App\Models\Orchard;
use App\Models\Season;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

class SeasonController extends Controller
{
    public function store(StoreSeasonRequest $request, Orchard $orchard): RedirectResponse
    {
        $orchard->seasons()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'เพิ่มรอบแล้ว']);

        return to_route('orchards.show', $orchard);
    }

    public function show(Season $season): Response
    {
        $season->load('orchard');

        $yieldRows = $season->applyDateWindow(
            Harvest::query()
                ->join('trees', 'trees.id', '=', 'harvests.tree_id')
                ->where('trees.orchard_id', $season->orchard_id),
            'harvests.harvested_on',
        )
            ->groupBy('trees.id', 'trees.code')
            ->orderByRaw('sum(harvests.weight_kg) desc')
            ->orderBy('trees.code')
            ->toBase()
            ->get([
                'trees.id as tree_id',
                'trees.code',
                DB::raw('sum(harvests.weight_kg) as weight_kg'),
                DB::raw('coalesce(sum(harvests.fruit_count), 0) as fruit_count'),
            ]);

        $treeYields = $yieldRows->map(fn (stdClass $row): array => [
            'treeId' => (int) $row->tree_id,
            'code' => $row->code,
            'weightKg' => (float) $row->weight_kg,
            'fruitCount' => (int) $row->fruit_count,
        ])->values();

        $sales = $season->applyDateWindow($season->orchard->sales()->getQuery(), 'sold_on');

        return Inertia::render('farm/seasons/show', [
            'season' => $season,
            'orchard' => $season->orchard,
            'totalYieldKg' => round($treeYields->sum('weightKg'), 2),
            'totalFruitCount' => $treeYields->sum('fruitCount'),
            'treeYields' => $treeYields,
            'salesTotal' => (float) (clone $sales)->sum('amount'),
            'salesWeightKg' => (float) (clone $sales)->sum('weight_kg'),
            'sales' => (clone $sales)->latest('sold_on')->latest('id')->get(),
            'expensesTotal' => (float) $season->expenses()->sum('amount'),
            'careRecordCount' => $season->applyDateWindow($season->orchard->careRecords()->getQuery(), 'performed_on')->count(),
        ]);
    }

    public function update(StoreSeasonRequest $request, Season $season): RedirectResponse
    {
        $season->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกรอบแล้ว']);

        return to_route('orchards.show', $season->orchard_id);
    }

    public function close(Request $request, Season $season): RedirectResponse
    {
        $validated = $request->validate([
            'ended_on' => ['nullable', 'date', 'after_or_equal:'.$season->started_on->toDateString()],
        ]);

        $season->update(['ended_on' => $validated['ended_on'] ?? now()->toDateString()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ปิดรอบแล้ว']);

        return to_route('orchards.show', $season->orchard_id);
    }

    public function destroy(Season $season): RedirectResponse
    {
        $season->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบรอบแล้ว']);

        return to_route('orchards.show', $season->orchard_id);
    }
}
