<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreCropCycleRequest;
use App\Http\Requests\Farm\UpdateCropCycleRequest;
use App\Models\ActivityType;
use App\Models\CropCycle;
use App\Models\ExpenseCategory;
use App\Models\Plot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class CropCycleController extends Controller
{
    public function store(StoreCropCycleRequest $request, Plot $plot): RedirectResponse
    {
        $plot->cropCycles()->create([
            ...$request->validated(),
            'fruit_variety_id' => $plot->fruit_variety_id,
            'recorded_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'เพิ่มรอบการผลิตแล้ว']);

        return to_route('plots.show', $plot);
    }

    public function update(UpdateCropCycleRequest $request, CropCycle $cropCycle): RedirectResponse
    {
        $validated = $request->validated();

        if (array_key_exists('flowering_date', $validated) && $validated['flowering_date'] !== null) {
            $cropCycle->loadMissing('fruitVariety');
            $cropCycle->recordFlowering(Carbon::parse($validated['flowering_date']));
            unset($validated['flowering_date']);
        }

        if ($validated !== []) {
            $cropCycle->update($validated);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'อัปเดตรอบการผลิตแล้ว']);

        return to_route('plots.show', $cropCycle->plot_id);
    }

    public function show(CropCycle $cropCycle): Response
    {
        $cropCycle->load([
            'plot',
            'fruitVariety.fruitType',
            'activities' => fn ($q) => $q->with('activityType')->latest('performed_on'),
            'expenses' => fn ($q) => $q->with('expenseCategory')->latest('spent_on'),
        ]);

        return Inertia::render('farm/crop-cycles/show', [
            'cropCycle' => $cropCycle,
            'totalDirectCost' => (float) $cropCycle->expenses->sum('amount'),
            'activityTypes' => ActivityType::orderBy('name')->get(['id', 'name']),
            'expenseCategories' => ExpenseCategory::orderBy('name')->get(),
        ]);
    }
}
