<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreHarvestRequest;
use App\Models\CropCycle;
use App\Models\Harvest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class HarvestController extends Controller
{
    public function store(StoreHarvestRequest $request, CropCycle $cropCycle): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $cropCycle, $request) {
            $harvest = $cropCycle->harvests()->create([
                'harvested_on' => $validated['harvested_on'],
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $item) {
                $harvest->items()->create([
                    'grade_id' => $item['grade_id'],
                    'weight_kg' => $item['weight_kg'],
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกการเก็บเกี่ยวแล้ว']);

        return to_route('crop-cycles.show', $cropCycle);
    }

    public function destroy(Harvest $harvest): RedirectResponse
    {
        $cropCycleId = $harvest->crop_cycle_id;
        $harvest->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบการเก็บเกี่ยวแล้ว']);

        return to_route('crop-cycles.show', $cropCycleId);
    }
}
