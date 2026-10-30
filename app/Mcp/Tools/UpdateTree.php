<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\RecordSerializer;
use App\Mcp\Support\TreeLookup;
use App\Models\Tree;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update_tree')]
#[Description('Only call after the user confirmed a summary in chat. Replaces the details of one tree found by its code: new_code, variety, planted_on and notes (send every field again; omitted optional fields are cleared, and omitting new_code keeps the code). Use set_tree_removed to mark a tree as gone. Pass orchard_id when the farm has more than one orchard. Returns the saved tree.')]
class UpdateTree extends FarmWriteTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'orchard_id' => $schema->integer()->description('Orchard id from get_farm_overview. Optional only when the farm has a single orchard.'),
            'code' => $schema->string()->description('Current tree code, for example A-001.')->required(),
            'new_code' => $schema->string()->description('New tree code, only when renaming the tree.'),
            'variety' => $schema->string()->description('Durian variety, for example หมอนทอง.'),
            'planted_on' => $schema->string()->description('Planting date, YYYY-MM-DD.'),
            'notes' => $schema->string()->description('Free-form notes.'),
        ];
    }

    protected function save(Request $request): Response
    {
        $validated = $this->validated($request, [
            'orchard_id' => ['nullable', 'integer'],
            'code' => ['required', 'string'],
            'new_code' => ['nullable', 'string', 'max:50'],
            'variety' => ['nullable', 'string', 'max:255'],
            'planted_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'code.required' => 'code is required; pass the tree code, for example A-001.',
            ...$this->dateMessages('planted_on'),
        ]);

        $tree = TreeLookup::resolve($validated['orchard_id'] ?? null, [$validated['code']])->firstOrFail();
        $newCode = trim($validated['new_code'] ?? '') ?: $tree->code;

        $taken = Tree::query()
            ->where('orchard_id', $tree->orchard_id)
            ->whereKeyNot($tree->id)
            ->whereRaw('lower(code) = ?', [mb_strtolower($newCode)])
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'new_code' => "Another tree in this orchard already has the code {$newCode}; choose another code.",
            ]);
        }

        $tree->update([
            'code' => $newCode,
            'variety' => $validated['variety'] ?? null,
            'planted_on' => $validated['planted_on'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return Response::json(['tree' => RecordSerializer::serialize($tree)]);
    }
}
