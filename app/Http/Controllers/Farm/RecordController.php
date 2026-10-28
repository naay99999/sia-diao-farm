<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Farm\RecordFilterRequest;
use App\Models\CareRecord;
use App\Models\Harvest;
use App\Models\Observation;
use App\Models\Sale;
use App\Models\Tree;
use App\Support\IssueSummary;
use App\Support\RecordFilters;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class RecordController extends Controller
{
    public const PER_PAGE = 50;

    public function redirect(): RedirectResponse
    {
        return to_route('records.index', ['type' => 'care']);
    }

    public function index(RecordFilterRequest $request, string $type): Response|RedirectResponse
    {
        $filters = $request->filters();

        $records = match ($type) {
            'care' => $this->careRecords($filters),
            'observations' => $this->observations($filters),
            'harvests' => $this->harvests($filters),
            'sales' => $this->sales($filters),
            default => abort(404),
        };

        if ($records->total() > 0 && $records->currentPage() > $records->lastPage()) {
            return redirect()->route('records.index', [
                'type' => $type,
                ...$request->query(),
                'page' => $records->lastPage(),
            ]);
        }

        return Inertia::render('records/index', [
            'type' => $type,
            'records' => $records->withQueryString(),
            'filters' => $filters,
            ...RecordFilters::options(),
            ...($type === 'care' ? $this->careEditOptions() : []),
        ]);
    }

    /**
     * What the care record form needs beyond the row itself: each orchard's standing trees and the open problems
     * on them, in two queries however many rows the page shows. Lazy, so changing a filter or page can skip them.
     *
     * @return array{orchardTrees: Closure(): array<int, list<array{id: int, code: string}>>, openIssues: Closure(): array<int, array<string, mixed>>}
     */
    private function careEditOptions(): array
    {
        return [
            'orchardTrees' => fn (): array => Tree::query()->active()->orderBy('code')->get(['id', 'orchard_id', 'code'])
                ->groupBy('orchard_id')
                ->map(fn ($trees) => $trees->map->only(['id', 'code'])->values()->all())
                ->all(),
            'openIssues' => fn (): array => IssueSummary::openForFarm(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function careRecords(array $filters): LengthAwarePaginator
    {
        $query = CareRecord::query()->with(['orchard:id,name', 'trees:id,code,removed_on', 'issues:id', 'creator:id,name', 'updater:id,name']);

        return $this->newestFirst(RecordFilters::apply($query, $filters, 'performed_on', 'orchard_id'), 'performed_on')
            ->paginate(self::PER_PAGE)
            ->through(fn (CareRecord $record): array => $this->careRow($record));
    }

    /**
     * @return array<string, mixed>
     */
    private function careRow(CareRecord $record): array
    {
        return [
            ...$this->provenance($record),
            'id' => $record->id,
            'type' => $record->type->value,
            'typeLabel' => $record->type->label(),
            'performed_on' => $record->performed_on->toDateString(),
            'product' => $record->product,
            'quantity' => $record->quantity,
            'notes' => $record->notes,
            'trees' => $record->trees->map->only(['id', 'code', 'removed_on'])->all(),
            'issue_ids' => $record->issues->pluck('id')->all(),
            'orchard' => $record->orchard->only(['id', 'name']),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function observations(array $filters): LengthAwarePaginator
    {
        $query = Observation::query()->with(['tree.orchard:id,name', 'finding', 'issue', 'creator:id,name', 'updater:id,name']);

        return $this->newestFirst(RecordFilters::apply($query, $filters, 'observed_on', null, 'tree'), 'observed_on')
            ->paginate(self::PER_PAGE)
            ->through(fn (Observation $observation): array => $this->observationRow($observation));
    }

    /**
     * @return array<string, mixed>
     */
    private function observationRow(Observation $observation): array
    {
        return [
            ...$this->provenance($observation),
            'id' => $observation->id,
            'finding_id' => $observation->finding_id,
            'finding' => $observation->finding->only(['id', 'name', 'kind']),
            'tree_issue_id' => $observation->tree_issue_id,
            'observed_on' => $observation->observed_on->toDateString(),
            'severity' => $observation->severity?->value,
            'outcome' => $observation->outcome?->value,
            'notes' => $observation->notes,
            'issueStatus' => match (true) {
                $observation->issue === null => null,
                $observation->issue->isOpen() => 'open',
                default => 'resolved',
            },
            'tree' => $observation->tree->only(['id', 'code']),
            'orchard' => $observation->tree->orchard->only(['id', 'name']),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function harvests(array $filters): LengthAwarePaginator
    {
        $query = Harvest::query()->with(['tree.orchard:id,name', 'creator:id,name', 'updater:id,name']);

        return $this->newestFirst(RecordFilters::apply($query, $filters, 'harvested_on', null, 'tree'), 'harvested_on')
            ->paginate(self::PER_PAGE)
            ->through(fn (Harvest $harvest): array => $this->harvestRow($harvest));
    }

    /**
     * @return array<string, mixed>
     */
    private function harvestRow(Harvest $harvest): array
    {
        return [
            ...$this->provenance($harvest),
            'id' => $harvest->id,
            'harvested_on' => $harvest->harvested_on->toDateString(),
            'weight_kg' => $harvest->weight_kg,
            'fruit_count' => $harvest->fruit_count,
            'notes' => $harvest->notes,
            'tree' => $harvest->tree->only(['id', 'code']),
            'orchard' => $harvest->tree->orchard->only(['id', 'name']),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function sales(array $filters): LengthAwarePaginator
    {
        $query = Sale::query()->with(['orchard:id,name', 'creator:id,name', 'updater:id,name']);

        return $this->newestFirst(RecordFilters::apply($query, $filters, 'sold_on', 'orchard_id'), 'sold_on')
            ->paginate(self::PER_PAGE)
            ->through(fn (Sale $sale): array => $this->saleRow($sale));
    }

    /**
     * @return array<string, mixed>
     */
    private function saleRow(Sale $sale): array
    {
        return [
            ...$this->provenance($sale),
            'id' => $sale->id,
            'sold_on' => $sale->sold_on->toDateString(),
            'buyer_name' => $sale->buyer_name,
            'weight_kg' => $sale->weight_kg,
            'amount' => $sale->amount,
            'notes' => $sale->notes,
            'orchard' => $sale->orchard->only(['id', 'name']),
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function newestFirst(Builder $query, string $dateColumn): Builder
    {
        return $query->orderByDesc($dateColumn)->orderByDesc('id');
    }

    /**
     * @param  CareRecord|Observation|Harvest|Sale  $record
     * @return array{fromAi: bool, aiBy: string|null, createdBy: string|null}
     */
    private function provenance(Model $record): array
    {
        return [
            'fromAi' => $record->isFromAi(),
            'aiBy' => $record->aiActorName(),
            'createdBy' => $record->creator?->name,
        ];
    }
}
