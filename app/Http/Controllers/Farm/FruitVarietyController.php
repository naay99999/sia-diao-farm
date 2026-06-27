<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreFruitVarietyRequest;
use App\Http\Requests\Farm\UpdateFruitVarietyRequest;
use App\Models\FruitType;
use App\Models\FruitVariety;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FruitVarietyController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('farm/fruit-varieties/index', [
            'fruitVarieties' => FruitVariety::with('fruitType')->orderBy('name')->get(),
            'fruitTypes' => FruitType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreFruitVarietyRequest $request): RedirectResponse
    {
        FruitVariety::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'เพิ่มพันธุ์แล้ว']);

        return to_route('fruit-varieties.index');
    }

    public function update(UpdateFruitVarietyRequest $request, FruitVariety $fruitVariety): RedirectResponse
    {
        $fruitVariety->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'อัปเดตพันธุ์แล้ว']);

        return to_route('fruit-varieties.index');
    }

    public function destroy(FruitVariety $fruitVariety): RedirectResponse
    {
        $fruitVariety->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบพันธุ์แล้ว']);

        return to_route('fruit-varieties.index');
    }
}
