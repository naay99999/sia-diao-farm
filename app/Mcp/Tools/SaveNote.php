<?php

namespace App\Mcp\Tools;

use App\Enums\FindingKind;
use App\Mcp\Support\RecordSerializer;
use App\Mcp\Support\TreeLookup;
use App\Models\FarmNote;
use App\Models\Finding;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('save_note')]
#[Description('Only call after the user confirmed a summary in chat. Saves a piece of knowledge the farm wants to keep (what worked, what to avoid, a lesson), or replaces an existing note when id is given (then send every field again; omitted finding and tree are cleared). A note may be tied to a finding by name (a problem or growth stage; a new name is created with the given kind, default problem), to one tree by tree_code (pass orchard_id when the farm has more than one orchard), to both, or to neither. Returns the saved note with its id.')]
class SaveNote extends FarmWriteTool
{
    private const MAX_BODY_LENGTH = 5000;

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Id of an existing note to replace. Omit to create a new one.'),
            'body' => $schema->string()->description('The note text, at most '.self::MAX_BODY_LENGTH.' characters.')->required(),
            'finding' => $schema->string()->description('Name of the problem or growth stage the note is about, as listed in get_farm_overview. An unknown name creates a new finding.'),
            'kind' => $schema->string()->enum(array_column(FindingKind::cases(), 'value'))->description('Kind of a new finding: problem (default) or stage. Ignored when the finding already exists.'),
            'orchard_id' => $schema->integer()->description('Orchard id from get_farm_overview. Only used with tree_code; optional when the farm has a single orchard.'),
            'tree_code' => $schema->string()->description('Tree code, for example A-001, when the note is about one tree.'),
        ];
    }

    protected function save(Request $request): Response
    {
        $kinds = implode(', ', array_column(FindingKind::cases(), 'value'));

        $validated = $this->validated($request, [
            'id' => ['nullable', 'integer'],
            'body' => ['required', 'string', 'max:'.self::MAX_BODY_LENGTH],
            'finding' => ['nullable', 'string', 'max:255'],
            'kind' => ['nullable', 'string', Rule::enum(FindingKind::class)],
            'orchard_id' => ['nullable', 'integer'],
            'tree_code' => ['nullable', 'string'],
        ], [
            'body.required' => 'body is required; pass the text of the note.',
            'body.max' => 'body must be at most '.self::MAX_BODY_LENGTH.' characters.',
            'kind.enum' => "kind must be one of: {$kinds}.",
        ]);

        $note = isset($validated['id'])
            ? $this->existing(FarmNote::query(), (int) $validated['id'], 'note', 'list_notes')
            : new FarmNote;

        $findingName = trim((string) ($validated['finding'] ?? ''));
        $treeCode = trim((string) ($validated['tree_code'] ?? ''));

        $finding = $findingName === ''
            ? null
            : Finding::resolveByName($findingName, FindingKind::from($validated['kind'] ?? FindingKind::Problem->value));
        $tree = $treeCode === ''
            ? null
            : TreeLookup::resolve($validated['orchard_id'] ?? null, [$treeCode])->first();

        $note->fill([
            'body' => $validated['body'],
            'finding_id' => $finding?->id,
            'tree_id' => $tree?->id,
        ])->save();

        return Response::json(['note' => RecordSerializer::serialize($note)]);
    }
}
