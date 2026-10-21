<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreOrchardRequest;
use App\Models\Orchard;
use Illuminate\Http\RedirectResponse;
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

    public function show(Orchard $orchard): Response
    {
        return Inertia::render('farm/orchards/show', [
            'orchard' => $orchard,
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
