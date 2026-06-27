<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreGradeRequest;
use App\Http\Requests\Farm\UpdateGradeRequest;
use App\Models\FruitType;
use App\Models\Grade;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GradeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('farm/grades/index', [
            'grades' => Grade::with('fruitType')
                ->orderBy('fruit_type_id')
                ->orderBy('sort_order')
                ->get(),
            'fruitTypes' => FruitType::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreGradeRequest $request): RedirectResponse
    {
        Grade::create([
            ...$request->validated(),
            'sort_order' => $request->validated()['sort_order'] ?? 0,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'เพิ่มเกรดแล้ว']);

        return to_route('grades.index');
    }

    public function update(UpdateGradeRequest $request, Grade $grade): RedirectResponse
    {
        $grade->update([
            ...$request->validated(),
            'sort_order' => $request->validated()['sort_order'] ?? 0,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'อัปเดตเกรดแล้ว']);

        return to_route('grades.index');
    }

    public function destroy(Grade $grade): RedirectResponse
    {
        $grade->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบเกรดแล้ว']);

        return to_route('grades.index');
    }
}
