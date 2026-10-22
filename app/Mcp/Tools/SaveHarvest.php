<?php

namespace App\Mcp\Tools;

use App\Http\Requests\Farm\UpdateHarvestRequest;
use App\Mcp\Support\RecordSerializer;
use App\Mcp\Support\TreeLookup;
use App\Models\Harvest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('save_harvest')]
#[Description('Only call after the user confirmed a summary in chat. Records the harvest of one tree, or replaces an existing harvest when id is given (then send every field again; omitted optional fields are cleared, and the harvest cannot be moved to another tree). Harvests cannot be recorded for a tree that has been removed. Pass orchard_id when the farm has more than one orchard. Returns the saved harvest with its id.')]
class SaveHarvest extends FarmWriteTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Id of an existing harvest to replace. Omit to create a new one.'),
            'orchard_id' => $schema->integer()->description('Orchard id from get_farm_overview. Optional only when the farm has a single orchard.'),
            'tree_code' => $schema->string()->description('Tree code, for example A-001. Required when creating.'),
            'harvested_on' => $schema->string()->description('Harvest date, YYYY-MM-DD.')->required(),
            'weight_kg' => $schema->number()->min(0)->description('Total weight in kilograms, greater than 0.')->required(),
            'fruit_count' => $schema->integer()->min(1)->description('Number of fruits, if counted.'),
            'notes' => $schema->string()->description('Free-form notes.'),
        ];
    }

    protected function save(Request $request): Response
    {
        $updating = $request->get('id') !== null;

        $validated = $this->validated($request, [
            'id' => ['nullable', 'integer'],
            'orchard_id' => ['nullable', 'integer'],
            'tree_code' => [$updating ? 'nullable' : 'required', 'string'],
            ...(new UpdateHarvestRequest)->rules(),
        ], [
            'tree_code.required' => 'tree_code is required; pass the tree code, for example A-001.',
            'weight_kg.required' => 'weight_kg is required; pass the weight in kilograms.',
            'weight_kg.gt' => 'weight_kg must be greater than 0.',
            'fruit_count.min' => 'fruit_count must be at least 1; omit it if the fruits were not counted.',
            ...$this->dateMessages('harvested_on'),
        ]);

        $attributes = [
            'harvested_on' => $validated['harvested_on'],
            'weight_kg' => $validated['weight_kg'],
            'fruit_count' => $validated['fruit_count'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        $harvest = $updating
            ? $this->update($this->existing(Harvest::query(), (int) $validated['id'], 'harvest'), $validated, $attributes)
            : $this->create($validated, $attributes);

        return Response::json(['harvest' => RecordSerializer::serialize($harvest)]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    private function create(array $validated, array $attributes): Harvest
    {
        $tree = TreeLookup::resolve($validated['orchard_id'] ?? null, [$validated['tree_code']])->firstOrFail();

        if ($tree->isRemoved()) {
            throw ValidationException::withMessages([
                'tree_code' => "Tree {$tree->code} has been removed, so a harvest cannot be recorded for it.",
            ]);
        }

        return $tree->harvests()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    private function update(Harvest $harvest, array $validated, array $attributes): Harvest
    {
        $harvest->loadMissing('tree');

        if (isset($validated['tree_code'])) {
            $tree = TreeLookup::resolve($validated['orchard_id'] ?? $harvest->tree->orchard_id, [$validated['tree_code']])->firstOrFail();

            if ($tree->id !== $harvest->tree_id) {
                throw ValidationException::withMessages([
                    'tree_code' => "Harvest {$harvest->id} belongs to tree {$harvest->tree->code} and cannot be moved to {$tree->code}; omit tree_code, or delete the harvest and record a new one.",
                ]);
            }
        }

        $harvest->update($attributes);

        return $harvest;
    }
}
