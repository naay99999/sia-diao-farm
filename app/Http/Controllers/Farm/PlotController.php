<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StorePlotRequest;
use App\Http\Requests\Farm\UpdatePlotRequest;
use App\Models\FruitVariety;
use App\Models\Plot;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlotController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('farm/plots/index', [
            'plots' => Plot::with(['fruitVariety.fruitType', 'activeCropCycle'])
                ->orderBy('name')
                ->get()
                ->append('tree_age_years'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('farm/plots/create', [
            'fruitVarieties' => FruitVariety::with('fruitType')->orderBy('name')->get(),
        ]);
    }

    public function store(StorePlotRequest $request): RedirectResponse
    {
        Plot::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'เพิ่มแปลงแล้ว']);

        return to_route('plots.index');
    }

    public function show(Plot $plot): Response
    {
        $plot->load(['fruitVariety.fruitType', 'cropCycles' => fn ($q) => $q->latest('started_at')]);
        $plot->append('tree_age_years');

        return Inertia::render('farm/plots/show', [
            'plot' => $plot,
        ]);
    }

    public function edit(Plot $plot): Response
    {
        return Inertia::render('farm/plots/edit', [
            'plot' => $plot,
            'fruitVarieties' => FruitVariety::with('fruitType')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdatePlotRequest $request, Plot $plot): RedirectResponse
    {
        $plot->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'อัปเดตแปลงแล้ว']);

        return to_route('plots.show', $plot);
    }

    public function destroy(Plot $plot): RedirectResponse
    {
        $plot->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบแปลงแล้ว']);

        return to_route('plots.index');
    }
}
