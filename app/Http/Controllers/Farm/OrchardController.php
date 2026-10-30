<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreOrchardRequest;
use App\Models\CareRecord;
use App\Models\Harvest;
use App\Models\Orchard;
use App\Models\Tree;
use App\Support\IssueSummary;
use App\Support\OrchardSummary;
use App\Support\TreeLayout;
use App\Support\TreeQrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrchardController extends Controller
{
    /** How many of the newest sales the orchard page lists; the records page has the rest. */
    public const RECENT_SALES = 20;

    public function index(): Response
    {
        $orchards = Orchard::query()
            ->withCount(['trees' => fn ($query) => $query->active()])
            ->with('currentSeason')
            ->orderBy('name')
            ->get();

        $yields = OrchardSummary::openSeasonYields($orchards->pluck('currentSeason')->filter()->pluck('id')->all());

        return Inertia::render('farm/orchards/index', [
            'orchards' => $orchards->map(fn (Orchard $orchard): array => [
                ...$orchard->toArray(),
                'trees_count' => $orchard->trees_count,
                'current_season' => $orchard->currentSeason?->name,
                'current_yield_kg' => $yields[$orchard->id] ?? 0,
            ]),
        ]);
    }

    public function store(StoreOrchardRequest $request): RedirectResponse
    {
        $orchard = Orchard::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'เพิ่มสวนแล้ว']);

        return to_route('orchards.show', $orchard);
    }

    public function show(Request $request, Orchard $orchard): Response
    {
        $showRemoved = $request->boolean('show_removed');
        $currentSeason = $orchard->currentSeason;

        /** @var array<int, float> $currentSeasonYieldKg Harvest kg per tree id in the open season (trees without harvests absent). */
        $currentSeasonYieldKg = $currentSeason === null ? [] : $currentSeason
            ->applyDateWindow(
                Harvest::query()
                    ->join('trees', 'trees.id', '=', 'harvests.tree_id')
                    ->where('trees.orchard_id', $orchard->id),
                'harvests.harvested_on',
            )
            ->groupBy('harvests.tree_id')
            ->selectRaw('harvests.tree_id, sum(harvests.weight_kg) as weight_kg')
            ->pluck('weight_kg', 'tree_id')
            ->map(fn ($weight): float => (float) $weight)
            ->all();

        $trees = $orchard->trees()
            ->when(! $showRemoved, fn ($query) => $query->active())
            ->orderBy('code')
            ->get();
        $activeTrees = $trees->reject(fn (Tree $tree): bool => $tree->isRemoved())->values();
        $openIssues = IssueSummary::openForOrchard($orchard);

        return Inertia::render('farm/orchards/show', [
            'orchard' => $orchard,
            'trees' => $trees,
            'treeLayout' => TreeLayout::arrange($activeTrees, TreeLayout::statusesOf($openIssues)),
            'showRemoved' => $showRemoved,
            'recentCareRecords' => $orchard->careRecords()
                ->with(['trees:id,code,removed_on', 'issues:id'])
                ->latest('performed_on')
                ->latest('id')
                ->limit(10)
                ->get()
                ->each(function (CareRecord $record): void {
                    $record->setAttribute('issue_ids', $record->issues->pluck('id')->all());
                    $record->unsetRelation('issues');
                }),
            'dueIssues' => IssueSummary::due($openIssues),
            'openIssues' => $openIssues,
            'sales' => $orchard->sales()->latest('sold_on')->latest('id')->limit(self::RECENT_SALES)->get(),
            'salesSummary' => $this->salesSummary($orchard),
            'currentSeason' => $currentSeason,
            'currentSeasonYieldKg' => (object) $currentSeasonYieldKg,
            'seasons' => $orchard->seasons()
                ->whereNotNull('ended_on')
                ->orderByDesc('started_on')
                ->get(),
        ]);
    }

    /**
     * Totals over every sale of the orchard, since the page lists only the newest ones.
     *
     * @return array{count: int, amount: float, weightKg: float}
     */
    private function salesSummary(Orchard $orchard): array
    {
        $totals = $orchard->sales()->toBase()
            ->selectRaw('count(*) as count, coalesce(sum(amount), 0) as amount, coalesce(sum(weight_kg), 0) as weight_kg')
            ->first();

        return [
            'count' => (int) $totals->count,
            'amount' => (float) $totals->amount,
            'weightKg' => (float) $totals->weight_kg,
        ];
    }

    public function qr(Orchard $orchard): Response
    {
        $trees = $orchard->trees()->active()->orderBy('code')->get();
        $svgs = TreeQrCode::svgs($trees);

        return Inertia::render('farm/orchards/qr', [
            'orchard' => $orchard,
            'labels' => $trees
                ->map(fn (Tree $tree): array => [
                    'code' => $tree->code,
                    'orchardName' => $orchard->name,
                    'svg' => $svgs[$tree->id],
                ])
                ->all(),
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
        if ($orchard->hasRecords()) {
            return back()->withErrors(['orchard' => 'ลบไม่ได้เพราะสวนนี้มีบันทึกแล้ว']);
        }

        $orchard->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบสวนแล้ว']);

        return to_route('orchards.index');
    }
}
