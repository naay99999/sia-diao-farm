<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreActivityRequest;
use App\Models\Activity;
use App\Models\CropCycle;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ActivityController extends Controller
{
    public function store(StoreActivityRequest $request, CropCycle $cropCycle): RedirectResponse
    {
        $validated = $request->validated();

        $activity = $cropCycle->activities()->create([
            'activity_type_id' => $validated['activity_type_id'],
            'performed_on' => $validated['performed_on'],
            'notes' => $validated['notes'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);

        if (! empty($validated['cost'])) {
            $activity->expenses()->create([
                'expense_category_id' => $validated['expense_category_id'],
                'amount' => $validated['cost'],
                'spent_on' => $validated['performed_on'],
                'crop_cycle_id' => $cropCycle->id,
                'recorded_by' => $request->user()->id,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกกิจกรรมแล้ว']);

        return to_route('crop-cycles.show', $cropCycle);
    }

    public function destroy(Activity $activity): RedirectResponse
    {
        $cropCycleId = $activity->crop_cycle_id;
        $activity->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบกิจกรรมแล้ว']);

        return to_route('crop-cycles.show', $cropCycleId);
    }
}
