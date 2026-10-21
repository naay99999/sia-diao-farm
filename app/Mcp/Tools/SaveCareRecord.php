<?php

namespace App\Mcp\Tools;

use App\Actions\Farm\SaveCareRecord as SaveCareRecordAction;
use App\Enums\CareType;
use App\Mcp\Support\RecordSerializer;
use App\Mcp\Support\TreeLookup;
use App\Models\CareRecord;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('save_care_record')]
#[Description('Only call after the user confirmed a summary in chat. Creates a care record (fertilizer, pesticide or other work) for one or more trees, or replaces an existing one when id is given (then send every field again; omitted optional fields are cleared). issue_ids are the open tree issues this care treats: when omitted on a new pesticide record, every open issue on the chosen trees is linked and their follow-up moves to 7 days after performed_on; on an update, omitting it keeps the current links. Pass orchard_id when the farm has more than one orchard. Returns the saved care_record with its id.')]
class SaveCareRecord extends FarmWriteTool
{
    public function __construct(private SaveCareRecordAction $saveCareRecord) {}

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Id of an existing care record to replace. Omit to create a new one.'),
            'orchard_id' => $schema->integer()->description('Orchard id from get_farm_overview. Optional only when the farm has a single orchard.'),
            'type' => $schema->string()->enum(array_column(CareType::cases(), 'value'))->description('Kind of care.')->required(),
            'performed_on' => $schema->string()->description('Date the work was done, YYYY-MM-DD.')->required(),
            'product' => $schema->string()->description('Product used, for example the pesticide or fertilizer name.'),
            'quantity' => $schema->string()->description('Amount used, free text such as "2 kg per tree".'),
            'notes' => $schema->string()->description('Free-form notes.'),
            'tree_codes' => $schema->array()->items($schema->string())->min(1)->description('Tree codes the work was done on, for example ["A-001", "A-002"].')->required(),
            'issue_ids' => $schema->array()->items($schema->integer())->description('Ids of open tree issues this care treats. Omit to use the default described above; send [] to link nothing.'),
        ];
    }

    protected function save(Request $request): Response
    {
        $types = implode(', ', array_column(CareType::cases(), 'value'));

        $validated = $this->validated($request, [
            'id' => ['nullable', 'integer'],
            'orchard_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::enum(CareType::class)],
            'performed_on' => ['required', 'date'],
            'product' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'tree_codes' => ['required', 'array', 'min:1'],
            'tree_codes.*' => ['required', 'string'],
            'issue_ids' => ['nullable', 'array'],
            'issue_ids.*' => ['integer'],
        ], [
            'type.required' => "type is required; use one of: {$types}.",
            'type.enum' => "type must be one of: {$types}.",
            'tree_codes.required' => 'tree_codes is required; pass the tree codes, for example ["A-001"].',
            'tree_codes.min' => 'tree_codes needs at least one tree code.',
            ...$this->dateMessages('performed_on'),
        ]);

        $record = isset($validated['id'])
            ? $this->existing(CareRecord::query(), (int) $validated['id'], 'care record')
            : null;
        $orchard = $this->orchardFor($record, $validated['orchard_id'] ?? null, 'Care record');
        $treeIds = TreeLookup::resolve($orchard->id, $validated['tree_codes'])->pluck('id')->all();
        $type = CareType::from($validated['type']);
        $issueIds = $validated['issue_ids'] ?? ($record === null ? $this->saveCareRecord->defaultIssueIds($type, $treeIds) : null);

        $saved = $this->saveCareRecord->handle($orchard, [
            'type' => $type,
            'performed_on' => $validated['performed_on'],
            'product' => $validated['product'] ?? null,
            'quantity' => $validated['quantity'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'tree_ids' => $treeIds,
            'issue_ids' => $issueIds,
        ], $record);

        return Response::json(['care_record' => RecordSerializer::serialize($saved)]);
    }
}
