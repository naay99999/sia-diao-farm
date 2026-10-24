<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreHarvestBatchRequest;
use App\Http\Requests\Farm\StoreHarvestRequest;
use App\Http\Requests\Farm\UpdateHarvestRequest;
use App\Models\Harvest;
use App\Models\Orchard;
use App\Models\Tree;
use App\Support\ReturnTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HarvestController extends Controller
{
    public function create(Orchard $orchard): Response
    {
        return Inertia::render('farm/harvests/create', [
            'orchard' => $orchard,
            'trees' => $orchard->trees()->active()->orderBy('code')->get(['id', 'code', 'variety']),
        ]);
    }

    public function store(StoreHarvestRequest $request, Tree $tree): RedirectResponse
    {
        $tree->harvests()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกผลผลิตแล้ว']);

        return to_route('trees.show', $tree);
    }

    public function storeBatch(StoreHarvestBatchRequest $request, Orchard $orchard): RedirectResponse
    {
        $harvestedOn = $request->validated('harvested_on');
        $rows = $request->validated('rows');

        $orchard->getConnection()->transaction(function () use ($rows, $harvestedOn): void {
            foreach ($rows as $row) {
                Harvest::query()->create([
                    'tree_id' => $row['tree_id'],
                    'harvested_on' => $harvestedOn,
                    'weight_kg' => $row['weight_kg'],
                    'fruit_count' => $row['fruit_count'] ?? null,
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกผลผลิต '.count($rows).' ต้นแล้ว']);

        return to_route('orchards.show', $orchard);
    }

    public function update(UpdateHarvestRequest $request, Harvest $harvest): RedirectResponse
    {
        $harvest->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกผลผลิตแล้ว']);

        return ReturnTo::recordsOr($request, back());
    }

    public function destroy(Request $request, Harvest $harvest): RedirectResponse
    {
        $harvest->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบบันทึกผลผลิตแล้ว']);

        return ReturnTo::recordsOr($request, back());
    }
}
