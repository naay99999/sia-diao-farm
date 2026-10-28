<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\RecordSerializer;
use App\Mcp\Support\TreeLookup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Date;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('set_tree_removed')]
#[Description('Only call after the user confirmed a summary in chat. Marks one tree as no longer in the orchard (died, cut down) or brings it back. A removed tree keeps its history but is left out of the overview counts, and new harvests cannot be recorded for it. Pass orchard_id when the farm has more than one orchard. Returns the saved tree.')]
class SetTreeRemoved extends FarmWriteTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'orchard_id' => $schema->integer()->description('Orchard id from get_farm_overview. Optional only when the farm has a single orchard.'),
            'code' => $schema->string()->description('Tree code, for example A-001.')->required(),
            'removed' => $schema->boolean()->description('true to mark the tree as removed, false to bring it back.')->required(),
            'removed_on' => $schema->string()->description('When the tree was removed, YYYY-MM-DD. Default is today. Ignored when removed is false.'),
        ];
    }

    protected function save(Request $request): Response
    {
        $validated = $this->validated($request, [
            'orchard_id' => ['nullable', 'integer'],
            'code' => ['required', 'string'],
            'removed' => ['required', 'boolean'],
            'removed_on' => ['nullable', 'date'],
        ], [
            'code.required' => 'code is required; pass the tree code, for example A-001.',
            'removed.required' => 'removed is required; true to mark the tree as removed, false to bring it back.',
            ...$this->dateMessages('removed_on'),
        ]);

        $tree = TreeLookup::resolve($validated['orchard_id'] ?? null, [$validated['code']])->firstOrFail();

        $tree->update([
            'removed_on' => $validated['removed']
                ? ($validated['removed_on'] ?? Date::today()->toDateString())
                : null,
        ]);

        return Response::json(['tree' => RecordSerializer::serialize($tree)]);
    }
}
