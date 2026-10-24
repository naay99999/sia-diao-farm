<?php

namespace App\Http\Controllers\Farm;

use App\Actions\Farm\RecordFollowUp;
use App\Actions\Farm\RecordObservation;
use App\Enums\FindingKind;
use App\Enums\Severity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreFollowUpRequest;
use App\Http\Requests\Farm\StoreObservationRequest;
use App\Http\Requests\Farm\UpdateObservationRequest;
use App\Models\Finding;
use App\Models\Observation;
use App\Models\Tree;
use App\Models\TreeIssue;
use App\Support\ReturnTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ObservationController extends Controller
{
    public function store(StoreObservationRequest $request, Tree $tree, RecordObservation $recordObservation): RedirectResponse
    {
        $finding = $request->filled('finding_id')
            ? Finding::query()->findOrFail($request->integer('finding_id'))
            : Finding::resolveByName($request->string('finding_name')->toString(), FindingKind::from($request->string('kind')->toString()));

        $recordObservation->handle(
            $tree,
            $finding,
            [
                'observed_on' => $request->string('observed_on')->toString(),
                'severity' => $request->enum('severity', Severity::class),
                'notes' => $request->filled('notes') ? $request->string('notes')->toString() : null,
                'follow_up_on' => $request->date('follow_up_on'),
            ],
            $request->file('photos', []),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกสภาพต้นแล้ว']);

        return to_route('trees.show', $tree);
    }

    public function storeFollowUp(StoreFollowUpRequest $request, TreeIssue $treeIssue, RecordFollowUp $recordFollowUp): RedirectResponse
    {
        $recordFollowUp->handle(
            $treeIssue,
            [
                'observed_on' => $request->string('observed_on')->toString(),
                'outcome' => $request->string('outcome')->toString(),
                'notes' => $request->filled('notes') ? $request->string('notes')->toString() : null,
                'next_follow_up_on' => $request->date('next_follow_up_on'),
            ],
            $request->file('photos', []),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกการดูซ้ำแล้ว']);

        return to_route('trees.show', $treeIssue->tree_id);
    }

    public function update(UpdateObservationRequest $request, Observation $observation): RedirectResponse
    {
        $observation->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกสภาพต้นแล้ว']);

        return ReturnTo::recordsOr($request, to_route('trees.show', $observation->tree_id));
    }

    public function destroy(Request $request, Observation $observation): RedirectResponse
    {
        $observation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบบันทึกสภาพต้นแล้ว']);

        return ReturnTo::recordsOr($request, to_route('trees.show', $observation->tree_id));
    }
}
