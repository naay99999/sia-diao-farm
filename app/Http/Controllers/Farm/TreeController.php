<?php

namespace App\Http\Controllers\Farm;

use App\Enums\FindingKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreTreeBatchRequest;
use App\Http\Requests\Farm\StoreTreeRequest;
use App\Http\Requests\Farm\UpdateTreeRequest;
use App\Models\Finding;
use App\Models\Orchard;
use App\Models\Season;
use App\Models\Tree;
use App\Support\IssueSummary;
use App\Support\TreeTimeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TreeController extends Controller
{
    public function store(StoreTreeRequest $request, Orchard $orchard): RedirectResponse
    {
        $orchard->trees()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'เพิ่มต้นไม้แล้ว']);

        return to_route('orchards.show', $orchard);
    }

    public function storeBatch(StoreTreeBatchRequest $request, Orchard $orchard): RedirectResponse
    {
        $variety = $request->validated('variety');

        $orchard->getConnection()->transaction(function () use ($request, $orchard, $variety): void {
            foreach ($request->codes() as $code) {
                $orchard->trees()->create(['code' => $code, 'variety' => $variety]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'เพิ่มต้นไม้ '.count($request->codes()).' ต้นแล้ว']);

        return to_route('orchards.show', $orchard);
    }

    public function show(Tree $tree): Response
    {
        return Inertia::render('farm/trees/show', [
            'tree' => $tree->load('orchard'),
            'timeline' => TreeTimeline::for($tree),
            'openIssues' => IssueSummary::forIssues(
                $tree->issues()->open()->with(['tree:id,code', 'finding:id,name'])->orderBy('follow_up_on')->orderBy('id')->get(),
            ),
            'latestStage' => $this->latestStage($tree),
            'findings' => Finding::query()->orderBy('kind')->orderBy('id')->get(['id', 'name', 'kind']),
            'activeTrees' => $tree->orchard->trees()->active()->orderBy('code')->get(['id', 'code']),
            'seasonYields' => $this->seasonYields($tree),
        ]);
    }

    /**
     * @return array{finding: string, observedOn: string, inCurrentSeason: bool}|null
     */
    private function latestStage(Tree $tree): ?array
    {
        $observation = $tree->observations()
            ->whereRelation('finding', 'kind', FindingKind::Stage)
            ->with('finding')
            ->orderByDesc('observed_on')
            ->orderByDesc('id')
            ->first();

        if ($observation === null) {
            return null;
        }

        $currentSeason = $tree->orchard->currentSeason;

        return [
            'finding' => $observation->finding->name,
            'observedOn' => $observation->observed_on->toDateString(),
            'inCurrentSeason' => $currentSeason?->containsDate($observation->observed_on) ?? false,
        ];
    }

    /**
     * @return array<int, array{seasonId: int, seasonName: string, weightKg: float, fruitCount: int}>
     */
    private function seasonYields(Tree $tree): array
    {
        return $tree->orchard->seasons()
            ->orderByDesc('started_on')
            ->get()
            ->map(function (Season $season) use ($tree): array {
                $query = $season->applyDateWindow($tree->harvests()->getQuery(), 'harvested_on');

                return [
                    'seasonId' => $season->id,
                    'seasonName' => $season->name,
                    'weightKg' => (float) $query->sum('weight_kg'),
                    'fruitCount' => (int) $query->sum('fruit_count'),
                ];
            })
            ->all();
    }

    public function update(UpdateTreeRequest $request, Tree $tree): RedirectResponse
    {
        $tree->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกต้นไม้แล้ว']);

        return to_route('trees.show', $tree);
    }

    public function remove(Request $request, Tree $tree): RedirectResponse
    {
        $validated = $request->validate(['removed_on' => ['nullable', 'date']]);

        $tree->update(['removed_on' => $validated['removed_on'] ?? now()->toDateString()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'บันทึกว่าต้นไม้ไม่อยู่แล้ว']);

        return to_route('trees.show', $tree);
    }

    public function restore(Tree $tree): RedirectResponse
    {
        $tree->update(['removed_on' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'กู้คืนต้นไม้แล้ว']);

        return to_route('trees.show', $tree);
    }

    public function destroy(Tree $tree): RedirectResponse
    {
        if ($tree->hasRecords()) {
            return back()->withErrors(['tree' => 'ลบไม่ได้เพราะต้นนี้มีบันทึกแล้ว']);
        }

        $tree->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบต้นไม้แล้ว']);

        return to_route('orchards.show', $tree->orchard_id);
    }

    public function qr(Tree $tree): RedirectResponse
    {
        return to_route('trees.show', $tree);
    }
}
