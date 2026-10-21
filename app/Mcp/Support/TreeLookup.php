<?php

namespace App\Mcp\Support;

use App\Models\Orchard;
use App\Models\Tree;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Turns the tree codes an AI client sends into trees, inferring the orchard when the farm has only one.
 */
class TreeLookup
{
    /**
     * Removed trees are returned too; the caller decides whether that is acceptable.
     *
     * @param  array<int, string>  $codes
     * @return Collection<int, Tree> In the order the codes were given, without duplicates.
     *
     * @throws ValidationException
     */
    public static function resolve(?int $orchardId, array $codes): Collection
    {
        $orchard = self::orchard($orchardId);
        $wanted = collect($codes)->map(fn (string $code): string => trim($code))->filter()->unique()->values();

        $found = Tree::query()
            ->where('orchard_id', $orchard->id)
            ->whereIn('code', $wanted)
            ->get()
            ->keyBy(fn (Tree $tree): string => mb_strtolower($tree->code));

        $missing = $wanted->reject(fn (string $code): bool => $found->has(mb_strtolower($code)));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'tree_codes' => "Tree code(s) not found in orchard \"{$orchard->name}\" (id {$orchard->id}): {$missing->join(', ')}. Check the codes, or pass the right orchard_id from get_farm_overview.",
            ]);
        }

        return $wanted->map(fn (string $code): Tree => $found->get(mb_strtolower($code)))->values();
    }

    /**
     * The given orchard, or the only one when the farm has a single orchard.
     *
     * @throws ValidationException
     */
    public static function orchard(?int $orchardId): Orchard
    {
        if ($orchardId !== null) {
            return Orchard::query()->find($orchardId) ?? throw ValidationException::withMessages([
                'orchard_id' => "Orchard {$orchardId} does not exist; pass an orchard_id from get_farm_overview.",
            ]);
        }

        $orchards = Orchard::query()->orderBy('id')->limit(2)->get();

        return match ($orchards->count()) {
            0 => throw ValidationException::withMessages(['orchard_id' => 'No orchards exist yet; create one in the app first.']),
            1 => $orchards->first(),
            default => throw ValidationException::withMessages(['orchard_id' => 'Multiple orchards exist; pass orchard_id from get_farm_overview.']),
        };
    }
}
