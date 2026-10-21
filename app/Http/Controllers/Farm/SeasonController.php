<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreSeasonRequest;
use App\Models\Orchard;
use App\Models\Season;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

        return Inertia::render('farm/seasons/show', [
            'season' => $season,
            'orchard' => $season->orchard,
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
