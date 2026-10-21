<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreSaleRequest;
use App\Models\Orchard;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SaleController extends Controller
{
    public function store(StoreSaleRequest $request, Orchard $orchard): RedirectResponse
    {
        $orchard->sales()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกการขายแล้ว']);

        return to_route('orchards.show', $orchard);
    }

    public function update(StoreSaleRequest $request, Sale $sale): RedirectResponse
    {
        $sale->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกการขายแล้ว']);

        return to_route('orchards.show', $sale->orchard_id);
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        $sale->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบการขายแล้ว']);

        return to_route('orchards.show', $sale->orchard_id);
    }
}
