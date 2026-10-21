<?php

namespace App\Http\Controllers\Farm;

use App\Enums\CareType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreCareRecordRequest;
use App\Models\CareRecord;
use App\Models\Orchard;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CareRecordController extends Controller
{
    public function create(Orchard $orchard): Response
    {
        return Inertia::render('farm/care-records/create', [
            'orchard' => $orchard,
            'trees' => $orchard->trees()->active()->orderBy('code')->get(),
            'types' => collect(CareType::cases())
                ->map(fn (CareType $type) => ['value' => $type->value, 'label' => $type->label()])
                ->all(),
        ]);
    }

    public function store(StoreCareRecordRequest $request, Orchard $orchard): RedirectResponse
    {
        $treeIds = $request->validated('tree_ids');

        $orchard->getConnection()->transaction(function () use ($request, $orchard, $treeIds): void {
            $careRecord = $orchard->careRecords()->create($request->safe()->except(['tree_ids', 'return_to']));
            $careRecord->trees()->sync($treeIds);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกการดูแลแล้ว']);

        return $this->redirectAfterSave($request, $orchard->id, $treeIds);
    }

    public function update(StoreCareRecordRequest $request, CareRecord $careRecord): RedirectResponse
    {
        $treeIds = $request->validated('tree_ids');

        $careRecord->getConnection()->transaction(function () use ($request, $careRecord, $treeIds): void {
            $careRecord->update($request->safe()->except(['tree_ids', 'return_to']));
            $careRecord->trees()->sync($treeIds);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกการดูแลแล้ว']);

        return $this->redirectAfterSave($request, $careRecord->orchard_id, $treeIds);
    }

    public function destroy(CareRecord $careRecord): RedirectResponse
    {
        $careRecord->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบบันทึกแล้ว']);

        return back();
    }

    /**
     * @param  array<int, int|string>  $treeIds
     */
    private function redirectAfterSave(StoreCareRecordRequest $request, int $orchardId, array $treeIds): RedirectResponse
    {
        if ($request->validated('return_to') === 'tree') {
            if (count($treeIds) === 1) {
                return to_route('trees.show', $treeIds[0]);
            }

            if ($request->isMethod('PATCH')) {
                return back();
            }
        }

        return to_route('orchards.show', $orchardId);
    }
}
