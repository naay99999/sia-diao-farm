<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\RecordSerializer;
use App\Mcp\Support\TreeLookup;
use App\Models\FarmNote;
use App\Models\Finding;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_notes')]
#[Description('List the farm\'s saved knowledge notes, newest first. Without filters every note is returned. With finding (a problem or growth stage name from get_farm_overview) and/or tree_code (pass orchard_id when the farm has several orchards), you get the notes tied to that finding or that tree plus the general notes that are tied to neither, which apply everywhere. Check these notes before advising on a problem. Deleted notes are hidden unless include_deleted is true, and then carry deleted_at. At most 200 notes per call (default 50); has_more tells you when more exist.')]
#[IsReadOnly]
class ListNotes extends Tool
{
    private const DEFAULT_LIMIT = 50;

    private const MAX_LIMIT = 200;

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'finding' => $schema->string()->description('Name of a problem or growth stage, as listed in get_farm_overview.'),
            'orchard_id' => $schema->integer()->description('Orchard id from get_farm_overview. Only used with tree_code; optional when the farm has a single orchard.'),
            'tree_code' => $schema->string()->description('Tree code, for example A-001.'),
            'include_deleted' => $schema->boolean()->description('Also list soft-deleted notes (default false).')->default(false),
            'limit' => $schema->integer()->min(1)->max(self::MAX_LIMIT)->description('Maximum number of notes (default 50, max 200).')->default(self::DEFAULT_LIMIT),
        ];
    }

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'finding' => ['nullable', 'string'],
            'orchard_id' => ['nullable', 'integer'],
            'tree_code' => ['nullable', 'string'],
            'include_deleted' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ], [
            'limit.min' => 'limit must be between 1 and '.self::MAX_LIMIT.'.',
            'limit.max' => 'limit must be between 1 and '.self::MAX_LIMIT.'.',
        ]);

        $limit = (int) ($validated['limit'] ?? self::DEFAULT_LIMIT);
        $includeDeleted = (bool) ($validated['include_deleted'] ?? false);
        $findingName = trim((string) ($validated['finding'] ?? ''));
        $treeCode = trim((string) ($validated['tree_code'] ?? ''));

        $findingId = $findingName === '' ? null : $this->findingId($findingName);
        $treeId = $treeCode === '' ? null : TreeLookup::resolve($validated['orchard_id'] ?? null, [$treeCode])->firstOrFail()->id;

        $query = FarmNote::query()->with(['finding:id,name', 'tree:id,code,orchard_id']);

        if ($includeDeleted) {
            $query->withTrashed();
        }

        if ($findingId !== null || $treeId !== null) {
            $query->where(function (Builder $related) use ($findingId, $treeId): void {
                if ($findingId !== null) {
                    $related->orWhere('finding_id', $findingId);
                }

                if ($treeId !== null) {
                    $related->orWhere('tree_id', $treeId);
                }

                $related->orWhere(fn (Builder $general) => $general->whereNull('finding_id')->whereNull('tree_id'));
            });
        }

        $notes = $query->orderByDesc('created_at')->orderByDesc('id')->limit($limit + 1)->get();

        return Response::json([
            'notes' => $notes->take($limit)
                ->map(fn (FarmNote $note): array => RecordSerializer::serialize($note, $includeDeleted))
                ->values()
                ->all(),
            'has_more' => $notes->count() > $limit,
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function findingId(string $name): int
    {
        return Finding::query()->where('name', $name)->value('id') ?? throw ValidationException::withMessages([
            'finding' => "No finding named \"{$name}\" exists. Use a name from the findings list of get_farm_overview.",
        ]);
    }
}
