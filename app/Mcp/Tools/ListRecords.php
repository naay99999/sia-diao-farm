<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\RecordSerializer;
use App\Mcp\Support\RecordTypes;
use App\Mcp\Support\TreeLookup;
use App\Models\Tree;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_records')]
#[Description('List farm records of one type, newest first. type is one of care, harvest, sale, expense, observation, issue. Optional filters: orchard_id (required with tree_code when the farm has several orchards), tree_code (care, harvest, observation and issue only), from and to (YYYY-MM-DD, inclusive, applied to the record\'s own date). Every record carries its id, which the save, delete and restore tools take. Deleted records are hidden unless include_deleted is true, and then carry deleted_at. Notes are listed with list_notes instead. At most 200 records per call (default 50); has_more tells you when to narrow the dates.')]
#[IsReadOnly]
class ListRecords extends Tool
{
    private const DEFAULT_LIMIT = 50;

    private const MAX_LIMIT = 200;

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(RecordTypes::LISTABLE)->description('Kind of record to list.')->required(),
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
            'type' => ['required', 'string', 'in:'.implode(',', RecordTypes::LISTABLE)],
            'orchard_id' => ['nullable', 'integer'],
            'tree_code' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'include_deleted' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ], [
            'type.required' => 'type is required; use one of: '.implode(', ', RecordTypes::LISTABLE).'.',
            'type.in' => 'type must be one of: '.implode(', ', RecordTypes::LISTABLE).'.',
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
                ->map(fn (Model $record): array => RecordSerializer::serialize($record, $includeDeleted))
                ->values()
                ->all(),
            'has_more' => $records->count() > $limit,
        ]);
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
        $query = RecordTypes::modelClass($type)::query()->with($this->relations($type));

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
}
