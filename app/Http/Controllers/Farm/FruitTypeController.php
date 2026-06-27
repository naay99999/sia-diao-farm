<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreFruitTypeRequest;
use App\Http\Requests\Farm\UpdateFruitTypeRequest;
use App\Models\FruitType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FruitTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('farm/fruit-types/index', [
            'fruitTypes' => FruitType::withCount('varieties')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreFruitTypeRequest $request): RedirectResponse
    {
        FruitType::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'เพิ่มชนิดผลไม้แล้ว']);

        return to_route('fruit-types.index');
    }

    public function update(UpdateFruitTypeRequest $request, FruitType $fruitType): RedirectResponse
    {
        $fruitType->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'อัปเดตชนิดผลไม้แล้ว']);

        return to_route('fruit-types.index');
    }

    public function destroy(FruitType $fruitType): RedirectResponse
    {
        $fruitType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบชนิดผลไม้แล้ว']);

        return to_route('fruit-types.index');
    }
}
