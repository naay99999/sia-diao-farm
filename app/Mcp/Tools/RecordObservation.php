<?php

namespace App\Mcp\Tools;

use App\Actions\Farm\RecordObservation as RecordObservationAction;
use App\Enums\FindingKind;
use App\Enums\Severity;
use App\Mcp\Support\RecordSerializer;
use App\Mcp\Support\TreeLookup;
use App\Models\Finding;
use App\Models\Observation;
use App\Models\TreeIssue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('record_observation')]
#[Description('Only call after the user confirmed a summary in chat. Records what was seen on one or more trees: a problem (for example a pest or disease) or a growth stage (for example flowering). Creates one observation per tree, all or nothing. A problem is appended to the tree\'s open issue of the same finding, or opens a new issue due for follow-up in 7 days (or on follow_up_on). A finding name that does not exist yet is created with the given kind. Pass orchard_id when the farm has more than one orchard. With id, only observed_on, severity and notes of that existing observation are changed (the other arguments are ignored). Returns the saved observations with their ids and the issues they belong to.')]
class RecordObservation extends FarmWriteTool
{
    public function __construct(private RecordObservationAction $recordObservation) {}

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Id of an existing observation to correct. Only observed_on, severity and notes can be changed this way. Omit to record new observations.'),
            'orchard_id' => $schema->integer()->description('Orchard id from get_farm_overview. Optional only when the farm has a single orchard.'),
            'tree_codes' => $schema->array()->items($schema->string())->min(1)->description('Tree codes that were observed, for example ["A-001", "A-002"]. Required when creating.'),
            'finding' => $schema->string()->description('Name of what was seen, for example "ไรแดง" or "ออกดอก"; use a name from get_farm_overview when one fits. Required when creating.'),
            'kind' => $schema->string()->enum(array_column(FindingKind::cases(), 'value'))->description('"problem" for a pest, disease or damage; "stage" for a growth stage. Required when creating.'),
            'observed_on' => $schema->string()->description('Date it was seen, YYYY-MM-DD.')->required(),
            'severity' => $schema->string()->enum(array_column(Severity::cases(), 'value'))->description('How bad it is: low, medium or high.'),
            'notes' => $schema->string()->description('Free-form notes.'),
            'follow_up_on' => $schema->string()->description('When to check a problem again, YYYY-MM-DD, not before observed_on. Default is 7 days after observed_on.'),
        ];
    }

    protected function save(Request $request): Response
    {
        $updating = $request->get('id') !== null;
        $kinds = implode(', ', array_column(FindingKind::cases(), 'value'));
        $severities = implode(', ', array_column(Severity::cases(), 'value'));

        $validated = $this->validated($request, [
            'id' => ['nullable', 'integer'],
            'orchard_id' => ['nullable', 'integer'],
            'tree_codes' => $updating ? ['nullable', 'array'] : ['required', 'array', 'min:1'],
            'tree_codes.*' => ['required', 'string'],
            'finding' => $updating ? ['nullable', 'string'] : ['required', 'string', 'max:255'],
            'kind' => $updating ? ['nullable'] : ['required', Rule::enum(FindingKind::class)],
            'observed_on' => $updating ? ['sometimes', 'required', 'date'] : ['required', 'date'],
            'severity' => ['nullable', Rule::enum(Severity::class)],
            'notes' => ['nullable', 'string'],
            'follow_up_on' => ['nullable', 'date', 'after_or_equal:observed_on'],
        ], [
            'tree_codes.required' => 'tree_codes is required; pass the tree codes, for example ["A-001"].',
            'tree_codes.min' => 'tree_codes needs at least one tree code.',
            'finding.required' => 'finding is required; pass what was seen, for example "ไรแดง".',
            'kind.required' => "kind is required; use one of: {$kinds}.",
            'kind.enum' => "kind must be one of: {$kinds}.",
            'severity.enum' => "severity must be one of: {$severities}.",
            'follow_up_on.date' => 'follow_up_on must be a date such as 2026-10-21.',
            'follow_up_on.after_or_equal' => 'follow_up_on must not be earlier than observed_on.',
            ...$this->dateMessages('observed_on'),
        ]);

        if ($updating) {
            return $this->update((int) $validated['id'], $validated);
        }

        return $this->create($validated);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function update(int $id, array $validated): Response
    {
        $observation = $this->existing(Observation::query(), $id, 'observation');
        $observation->update(Arr::only($validated, ['observed_on', 'severity', 'notes']));

        return $this->respond(Observation::query()->whereKey($observation->id)->get());
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function create(array $validated): Response
    {
        $trees = TreeLookup::resolve($validated['orchard_id'] ?? null, $validated['tree_codes']);

        $observations = DB::transaction(function () use ($validated, $trees): Collection {
            $finding = Finding::resolveByName($validated['finding'], FindingKind::from($validated['kind']));

            return new Collection($trees->map(fn ($tree): Observation => $this->recordObservation->handle($tree, $finding, [
                'observed_on' => $validated['observed_on'],
                'severity' => $validated['severity'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'follow_up_on' => $validated['follow_up_on'] ?? null,
            ]))->all());
        });

        return $this->respond($observations);
    }

    /**
     * @param  Collection<int, Observation>  $observations
     */
    private function respond(Collection $observations): Response
    {
        $issueIds = $observations->pluck('tree_issue_id')->filter()->unique()->values();

        return Response::json([
            'observations' => $observations->map(fn (Observation $observation): array => RecordSerializer::serialize($observation))->all(),
            'issues' => TreeIssue::query()->whereKey($issueIds)->orderBy('id')->get()
                ->map(fn (TreeIssue $issue): array => RecordSerializer::serialize($issue))->all(),
        ]);
    }
}
