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

#[Name('add_trees')]
#[Description('Only call after the user confirmed a summary in chat. Adds new trees to an orchard, all with the same variety and planting date. Give either tree_codes (an explicit list) or prefix with count (and optionally start) to number them like the app does: prefix "A-", start 1, count 3 gives A-001, A-002, A-003. Nothing is saved if any code already exists in the orchard. Pass orchard_id when the farm has more than one orchard. Returns the added trees.')]
class AddTrees extends FarmWriteTool
{
    public const MAX_TREES = 500;

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'orchard_id' => $schema->integer()->description('Orchard id from get_farm_overview. Optional only when the farm has a single orchard.'),
            'tree_codes' => $schema->array()->items($schema->string())->min(1)->description('Codes of the trees to add, for example ["A-001", "A-002"]. Use this or prefix and count.'),
            'prefix' => $schema->string()->description('Code prefix for numbered trees, for example "A-". Use with count instead of tree_codes.'),
            'start' => $schema->integer()->min(1)->description('First number with prefix (default 1). Numbers are padded to 3 digits.'),
            'count' => $schema->integer()->min(1)->max(self::MAX_TREES)->description('How many numbered trees to add with prefix (max 500).'),
            'variety' => $schema->string()->description('Durian variety, for example หมอนทอง.'),
            'planted_on' => $schema->string()->description('Planting date, YYYY-MM-DD.'),
        ];
    }

    protected function save(Request $request): Response
    {
        $validated = $this->validated($request, [
            'orchard_id' => ['nullable', 'integer'],
            'tree_codes' => ['required_without:prefix', 'prohibits:prefix', 'array', 'min:1', 'max:'.self::MAX_TREES],
            'tree_codes.*' => ['required', 'string', 'max:50'],
            'prefix' => ['required_without:tree_codes', 'string', 'max:40'],
            'start' => ['nullable', 'integer', 'min:1', 'max:99999'],
            'count' => ['required_with:prefix', 'integer', 'min:1', 'max:'.self::MAX_TREES],
            'variety' => ['nullable', 'string', 'max:255'],
            'planted_on' => ['nullable', 'date'],
        ], [
            'tree_codes.required_without' => 'Pass either tree_codes, or prefix with count.',
            'prefix.required_without' => 'Pass either tree_codes, or prefix with count.',
            'tree_codes.prohibits' => 'Pass either tree_codes or prefix, not both.',
            'count.required_with' => 'count is required with prefix; pass how many trees to add.',
            ...$this->dateMessages('planted_on'),
        ]);

        $orchard = TreeLookup::orchard($validated['orchard_id'] ?? null);
        $codes = isset($validated['prefix'])
            ? $this->numberedCodes($validated['prefix'], (int) ($validated['start'] ?? 1), (int) $validated['count'])
            : $this->listedCodes($validated['tree_codes']);

        $existing = $orchard->trees()
            ->pluck('code')
            ->filter(fn (string $code): bool => in_array(mb_strtolower($code), array_map(mb_strtolower(...), $codes), true))
            ->values();

        if ($existing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'tree_codes' => "Tree code(s) already exist in orchard \"{$orchard->name}\" (id {$orchard->id}): {$existing->join(', ')}. Nothing was added; choose other codes.",
            ]);
        }

        $trees = $orchard->getConnection()->transaction(fn (): array => array_map(
            fn (string $code): Tree => $orchard->trees()->create([
                'code' => $code,
                'variety' => $validated['variety'] ?? null,
                'planted_on' => $validated['planted_on'] ?? null,
            ]),
            $codes,
        ));

        return Response::json([
            'orchard_id' => $orchard->id,
            'added' => count($trees),
            'trees' => array_map(fn (Tree $tree): array => RecordSerializer::serialize($tree), $trees),
        ]);
    }

    /**
     * @return list<string>
     */
    private function numberedCodes(string $prefix, int $start, int $count): array
    {
        return array_map(
            fn (int $number): string => $prefix.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            range($start, $start + $count - 1),
        );
    }

    /**
     * @param  array<int, string>  $codes
     * @return list<string>
     *
     * @throws ValidationException
     */
    private function listedCodes(array $codes): array
    {
        $trimmed = array_map(trim(...), $codes);
        $duplicates = array_keys(array_filter(array_count_values(array_map(mb_strtolower(...), $trimmed)), fn (int $times): bool => $times > 1));

        if (in_array('', $trimmed, true) || $duplicates !== []) {
            throw ValidationException::withMessages([
                'tree_codes' => 'tree_codes must not contain blank or repeated codes'.($duplicates === [] ? '.' : ': '.implode(', ', $duplicates).'.'),
            ]);
        }

        return array_values($trimmed);
    }
}
