<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreSaleRequest;
use App\Models\CropCycle;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SaleController extends Controller
{
    public function store(StoreSaleRequest $request, CropCycle $cropCycle): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $cropCycle, $request) {
            $sale = $cropCycle->sales()->create([
                'buyer_name' => $validated['buyer_name'],
                'sold_on' => $validated['sold_on'],
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $item) {
                $sale->items()->create([
                    'grade_id' => $item['grade_id'],
                    'weight_kg' => $item['weight_kg'],
                    'price_per_kg' => $item['price_per_kg'],
                    'subtotal' => round($item['weight_kg'] * $item['price_per_kg'], 2),
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกการขายแล้ว']);

        return to_route('crop-cycles.show', $cropCycle);
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        $cropCycleId = $sale->crop_cycle_id;
        $sale->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบการขายแล้ว']);

        return to_route('crop-cycles.show', $cropCycleId);
    }
}
