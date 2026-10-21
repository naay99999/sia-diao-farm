<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreOrchardRequest;
use App\Models\Orchard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrchardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('farm/orchards/index', [
            'orchards' => Orchard::orderBy('name')->get(),
        ]);
    }

    public function store(StoreOrchardRequest $request): RedirectResponse
    {
        $orchard = Orchard::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'เพิ่มสวนแล้ว']);

        return to_route('orchards.show', $orchard);
    }

    public function show(Request $request, Orchard $orchard): Response
    {
        $showRemoved = $request->boolean('show_removed');

        return Inertia::render('farm/orchards/show', [
            'orchard' => $orchard,
            'trees' => $orchard->trees()
                ->when(! $showRemoved, fn ($query) => $query->active())
                ->orderBy('code')
                ->get(),
            'showRemoved' => $showRemoved,
            'currentSeason' => $orchard->currentSeason,
            'seasons' => $orchard->seasons()
                ->whereNotNull('ended_on')
                ->orderByDesc('started_on')
                ->get(),
        ]);
    }

    public function update(StoreOrchardRequest $request, Orchard $orchard): RedirectResponse
    {
        $orchard->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกสวนแล้ว']);

        return to_route('orchards.show', $orchard);
    }

    public function destroy(Orchard $orchard): RedirectResponse
    {
        $orchard->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบสวนแล้ว']);

        return to_route('orchards.index');
    }
}
