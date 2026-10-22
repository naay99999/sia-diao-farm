<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\StoreOrchardRequest;
use App\Models\CareRecord;
use App\Models\Harvest;
use App\Models\Orchard;
use App\Models\Tree;
use App\Support\IssueSummary;
use App\Support\TreeQrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class OrchardController extends Controller
{
    public function index(): Response
    {
        $orchards = Orchard::query()
            ->withCount(['trees' => fn ($query) => $query->active()])
            ->with('currentSeason')
            ->orderBy('name')
            ->get();

        $yields = Harvest::query()
            ->join('trees', 'trees.id', '=', 'harvests.tree_id')
            ->join('seasons', function ($join): void {
                $join->on('seasons.orchard_id', '=', 'trees.orchard_id')
                    ->whereNull('seasons.ended_on')
                    ->whereRaw('date(harvests.harvested_on) >= date(seasons.started_on)');
            })
            ->whereDate('harvests.harvested_on', '<=', Date::today()->toDateString())
            ->groupBy('trees.orchard_id')
            ->selectRaw('trees.orchard_id, sum(harvests.weight_kg) as weight_kg')
            ->pluck('weight_kg', 'orchard_id')
            ->map(fn ($weight): float => (float) $weight);

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

        return Inertia::render('farm/orchards/show', [
            'orchard' => $orchard,
            'trees' => $orchard->trees()
                ->when(! $showRemoved, fn ($query) => $query->active())
                ->orderBy('code')
                ->get(),
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
            'dueIssues' => IssueSummary::dueForOrchard($orchard),
            'openIssues' => IssueSummary::openForOrchard($orchard),
            'activeTrees' => $orchard->trees()->active()->orderBy('code')->get(['id', 'code']),
            'sales' => $orchard->sales()->latest('sold_on')->latest('id')->get(),
            'currentSeason' => $currentSeason,
            'currentSeasonYieldKg' => (object) $currentSeasonYieldKg,
            'seasons' => $orchard->seasons()
                ->whereNotNull('ended_on')
                ->orderByDesc('started_on')
                ->get(),
        ]);
    }

    public function qr(Orchard $orchard): Response
    {
        return Inertia::render('farm/orchards/qr', [
            'orchard' => $orchard,
            'labels' => $orchard->trees()
                ->active()
                ->orderBy('code')
                ->get()
                ->map(fn (Tree $tree): array => [
                    'code' => $tree->code,
                    'orchardName' => $orchard->name,
                    'svg' => TreeQrCode::svg($tree),
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
        $orchard->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ลบสวนแล้ว']);

        return to_route('orchards.index');
    }
}
