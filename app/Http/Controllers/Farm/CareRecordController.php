<?php

namespace App\Http\Controllers\Farm;

use App\Actions\Farm\SaveCareRecord;
use App\Enums\CareType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreCareRecordRequest;
use App\Models\CareRecord;
use App\Models\Orchard;
use App\Support\IssueSummary;
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
            'openIssues' => IssueSummary::openForOrchard($orchard),
            'types' => collect(CareType::cases())
                ->map(fn (CareType $type) => ['value' => $type->value, 'label' => $type->label()])
                ->all(),
        ]);
    }

    public function store(StoreCareRecordRequest $request, Orchard $orchard, SaveCareRecord $saveCareRecord): RedirectResponse
    {
        $data = $this->careRecordData($request);

        $saveCareRecord->handle($orchard, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกการดูแลแล้ว']);

        return $this->redirectAfterSave($request, $orchard->id, $data['tree_ids']);
    }

    public function update(StoreCareRecordRequest $request, CareRecord $careRecord, SaveCareRecord $saveCareRecord): RedirectResponse
    {
        $data = $this->careRecordData($request);

        $saveCareRecord->handle($careRecord->orchard, $data, $careRecord);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกการดูแลแล้ว']);

        return $this->redirectAfterSave($request, $careRecord->orchard_id, $data['tree_ids']);
    }

    public function destroy(CareRecord $careRecord): RedirectResponse
    {
        $careRecord->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบบันทึกแล้ว']);

        return back();
    }

    /**
     * The form always sends the full selection, so a missing `issue_ids` means no issue is linked.
     *
     * @return array{type: CareType, performed_on: string, product: string|null, quantity: string|null, notes: string|null, tree_ids: array<int, int>, issue_ids: array<int, int>}
     */
    private function careRecordData(StoreCareRecordRequest $request): array
    {
        return [
            'type' => CareType::from($request->string('type')->toString()),
            'performed_on' => $request->string('performed_on')->toString(),
            'product' => $request->filled('product') ? $request->string('product')->toString() : null,
            'quantity' => $request->filled('quantity') ? $request->string('quantity')->toString() : null,
            'notes' => $request->filled('notes') ? $request->string('notes')->toString() : null,
            'tree_ids' => array_map(intval(...), $request->array('tree_ids')),
            'issue_ids' => array_map(intval(...), $request->array('issue_ids')),
        ];
    }

    /**
     * @param  array<int, int>  $treeIds
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
