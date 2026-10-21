<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\TreeLookup;
use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\Harvest;
use App\Models\Observation;
use App\Models\Sale;
use App\Models\Tree;
use App\Models\TreeIssue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List farm records of one type, newest first. type is one of care, harvest, sale, expense, observation, issue. Optional filters: orchard_id (required with tree_code when the farm has several orchards), tree_code (care, harvest, observation and issue only), from and to (YYYY-MM-DD, inclusive, applied to the record\'s own date). Every record carries its id, which the save, delete and restore tools take. Deleted records are hidden unless include_deleted is true, and then carry deleted_at. At most 200 records per call (default 50); has_more tells you when to narrow the dates.')]
#[IsReadOnly]
class ListRecords extends Tool
{
    private const TYPES = ['care', 'harvest', 'sale', 'expense', 'observation', 'issue'];

    private const DEFAULT_LIMIT = 50;

    private const MAX_LIMIT = 200;

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(self::TYPES)->description('Kind of record to list.')->required(),
            'orchard_id' => $schema->integer()->description('Only records of this orchard. Needed with tree_code when the farm has several orchards.'),
            'tree_code' => $schema->string()->description('Only records of this tree (care, harvest, observation and issue only).'),
            'from' => $schema->string()->description('Earliest record date, YYYY-MM-DD, inclusive.'),
            'to' => $schema->string()->description('Latest record date, YYYY-MM-DD, inclusive.'),
            'include_deleted' => $schema->boolean()->description('Also list soft-deleted records (default false).')->default(false),
            'limit' => $schema->integer()->min(1)->max(self::MAX_LIMIT)->description('Maximum number of records (default 50, max 200).')->default(self::DEFAULT_LIMIT),
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', self::TYPES)],
            'orchard_id' => ['nullable', 'integer'],
            'tree_code' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'include_deleted' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ], [
            'type.required' => 'type is required; use one of: '.implode(', ', self::TYPES).'.',
            'type.in' => 'type must be one of: '.implode(', ', self::TYPES).'.',
            'from.date' => 'from must be a date such as 2026-10-21.',
            'to.date' => 'to must be a date such as 2026-10-21.',
            'to.after_or_equal' => 'to must not be earlier than from.',
            'limit.min' => 'limit must be between 1 and '.self::MAX_LIMIT.'.',
            'limit.max' => 'limit must be between 1 and '.self::MAX_LIMIT.'.',
        ]);

        $type = $validated['type'];
        $limit = (int) ($validated['limit'] ?? self::DEFAULT_LIMIT);
        $includeDeleted = (bool) ($validated['include_deleted'] ?? false);

        $query = $this->query($type, $validated['orchard_id'] ?? null, $validated['tree_code'] ?? null);
        $dateColumn = $this->dateColumn($type);

        if ($includeDeleted) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        if (! empty($validated['from'])) {
            $query->whereDate($dateColumn, '>=', $validated['from']);
        }

        if (! empty($validated['to'])) {
            $query->whereDate($dateColumn, '<=', $validated['to']);
        }

        $records = $query->orderByDesc($dateColumn)->orderByDesc('id')->limit($limit + 1)->get();

        return Response::json([
            'type' => $type,
            'records' => $records->take($limit)
                ->map(fn (Model $record): array => $this->serialize($record, $includeDeleted))
                ->values()
                ->all(),
            'has_more' => $records->count() > $limit,
        ]);
    }

    /**
     * The one place that maps a record type to its model.
     *
     * @return class-string<Model>
     */
    private function modelClass(string $type): string
    {
        return match ($type) {
            'care' => CareRecord::class,
            'harvest' => Harvest::class,
            'sale' => Sale::class,
            'expense' => Expense::class,
            'observation' => Observation::class,
            'issue' => TreeIssue::class,
            default => throw ValidationException::withMessages(['type' => 'type must be one of: '.implode(', ', self::TYPES).'.']),
        };
    }

    private function dateColumn(string $type): string
    {
        return match ($type) {
            'care' => 'performed_on',
            'harvest' => 'harvested_on',
            'sale' => 'sold_on',
            'expense' => 'spent_on',
            'observation' => 'observed_on',
            default => 'opened_on',
        };
    }

    /**
     * @return array<int, string>
     */
    private function relations(string $type): array
    {
        return match ($type) {
            'care' => ['trees:id,code', 'issues:id'],
            'harvest' => ['tree:id,code'],
            'observation', 'issue' => ['tree:id,code', 'finding:id,name'],
            default => [],
        };
    }

    /**
     * @return Builder<Model>
     */
    private function query(string $type, ?int $orchardId, ?string $treeCode): Builder
    {
        $query = $this->modelClass($type)::query()->with($this->relations($type));

        if ($treeCode !== null && $treeCode !== '') {
            if (in_array($type, ['sale', 'expense'], true)) {
                throw ValidationException::withMessages(['tree_code' => 'tree_code only applies to care, harvest, observation and issue records; remove it or choose another type.']);
            }

            $tree = TreeLookup::resolve($orchardId, [$treeCode])->firstOrFail();

            return $type === 'care'
                ? $query->whereHas('trees', fn (Builder $trees) => $trees->whereKey($tree->id))
                : $query->where('tree_id', $tree->id);
        }

        if ($orchardId === null) {
            return $query;
        }

        $orchard = TreeLookup::orchard($orchardId);

        return match ($type) {
            'care', 'sale' => $query->where('orchard_id', $orchard->id),
            'expense' => $query->whereIn('season_id', $orchard->seasons()->select('id')),
            default => $query->whereIn('tree_id', Tree::query()->where('orchard_id', $orchard->id)->select('id')),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Model $record, bool $includeDeleted): array
    {
        $data = match (true) {
            $record instanceof CareRecord => [
                'id' => $record->id,
                'orchard_id' => $record->orchard_id,
                'type' => $record->type->value,
                'performed_on' => $record->performed_on->toDateString(),
                'product' => $record->product,
                'quantity' => $record->quantity,
                'notes' => $record->notes,
                'tree_codes' => $record->trees->sortBy('code')->pluck('code')->values()->all(),
                'issue_ids' => $record->issues->pluck('id')->sort()->values()->all(),
            ],
            $record instanceof Harvest => [
                'id' => $record->id,
                'tree_id' => $record->tree_id,
                'tree_code' => $record->tree->code,
                'harvested_on' => $record->harvested_on->toDateString(),
                'weight_kg' => (float) $record->weight_kg,
                'fruit_count' => $record->fruit_count,
                'notes' => $record->notes,
            ],
            $record instanceof Sale => [
                'id' => $record->id,
                'orchard_id' => $record->orchard_id,
                'sold_on' => $record->sold_on->toDateString(),
                'buyer_name' => $record->buyer_name,
                'weight_kg' => (float) $record->weight_kg,
                'amount' => (float) $record->amount,
                'notes' => $record->notes,
            ],
            $record instanceof Expense => [
                'id' => $record->id,
                'season_id' => $record->season_id,
                'category' => $record->category->value,
                'spent_on' => $record->spent_on->toDateString(),
                'amount' => (float) $record->amount,
                'notes' => $record->notes,
            ],
            $record instanceof Observation => [
                'id' => $record->id,
                'tree_id' => $record->tree_id,
                'tree_code' => $record->tree->code,
                'finding_id' => $record->finding_id,
                'finding' => $record->finding->name,
                'tree_issue_id' => $record->tree_issue_id,
                'observed_on' => $record->observed_on->toDateString(),
                'severity' => $record->severity?->value,
                'outcome' => $record->outcome?->value,
                'notes' => $record->notes,
            ],
            $record instanceof TreeIssue => [
                'id' => $record->id,
                'tree_id' => $record->tree_id,
                'tree_code' => $record->tree->code,
                'finding_id' => $record->finding_id,
                'finding' => $record->finding->name,
                'opened_on' => $record->opened_on->toDateString(),
                'follow_up_on' => $record->follow_up_on->toDateString(),
                'resolved_on' => $record->resolved_on?->toDateString(),
            ],
            default => ['id' => $record->getKey()],
        };

        $data['from_ai'] = method_exists($record, 'isFromAi') ? $record->isFromAi() : false;

        if ($includeDeleted) {
            $data['deleted_at'] = $record->getAttribute('deleted_at')?->toIso8601String();
        }

        return $data;
    }
}
