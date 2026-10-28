<?php

namespace App\Support;

use App\Models\Orchard;
use App\Models\Tree;
use App\Models\TreeIssue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

class TreeLayout
{
    /**
     * @return array{row: string, position: int}|null
     */
    public static function parseCode(string $code): ?array
    {
        if (preg_match('/^([A-Za-z]+)-?(\d+)$/u', trim($code), $matches) !== 1) {
            return null;
        }

        return ['row' => strtoupper($matches[1]), 'position' => (int) $matches[2]];
    }

    /**
     * Rows ordered by length then alphabetically; the unparseable-code row (null) comes last.
     *
     * @return array<int, array{row: string|null, trees: array<int, array{id: int, code: string, position: int|null, status: 'due'|'issue'|'normal'}>}>
     */
    public static function forOrchard(Orchard $orchard): array
    {
        return self::forOrchards(new Collection([$orchard]))[$orchard->id];
    }

    /**
     * The layout of each orchard, keyed by orchard id, in two queries however many orchards there are.
     *
     * @param  Collection<int, Orchard>  $orchards
     * @return array<int, array<int, array{row: string|null, trees: array<int, array{id: int, code: string, position: int|null, status: 'due'|'issue'|'normal'}>}>>
     */
    public static function forOrchards(Collection $orchards): array
    {
        $trees = Tree::query()->active()->whereIn('orchard_id', $orchards->modelKeys())->get(['id', 'orchard_id', 'code']);

        $today = Date::today()->toDateString();
        $statuses = [];

        TreeIssue::query()->open()->whereIn('tree_id', $trees->modelKeys())->get(['tree_id', 'follow_up_on'])
            ->each(function (TreeIssue $issue) use ($today, &$statuses): void {
                $isDue = $issue->follow_up_on->toDateString() <= $today;
                $statuses[$issue->tree_id] = $isDue || ($statuses[$issue->tree_id] ?? null) === 'due' ? 'due' : 'issue';
            });

        $treesByOrchard = $trees->groupBy('orchard_id');
        $layouts = [];

        foreach ($orchards as $orchard) {
            $layouts[$orchard->id] = self::arrange($treesByOrchard->get($orchard->id, []), $statuses);
        }

        return $layouts;
    }

    /**
     * Statuses by tree id from issue summaries the caller already has, for {@see self::arrange()}.
     *
     * @param  iterable<array{treeId: int, isDue: bool}>  $openIssues
     * @return array<int, 'due'|'issue'>
     */
    public static function statusesOf(iterable $openIssues): array
    {
        $statuses = [];

        foreach ($openIssues as $issue) {
            $statuses[$issue['treeId']] = $issue['isDue'] || ($statuses[$issue['treeId']] ?? null) === 'due' ? 'due' : 'issue';
        }

        return $statuses;
    }

    /**
     * Lay out trees already loaded, marking each with its status (trees absent from `$statuses` are normal).
     *
     * @param  iterable<Tree>  $trees
     * @param  array<int, 'due'|'issue'>  $statuses
     * @return array<int, array{row: string|null, trees: array<int, array{id: int, code: string, position: int|null, status: 'due'|'issue'|'normal'}>}>
     */
    public static function arrange(iterable $trees, array $statuses): array
    {
        $rows = [];
        $otherTrees = [];

        foreach ($trees as $tree) {
            $parsed = self::parseCode($tree->code);
            $entry = [
                'id' => $tree->id,
                'code' => $tree->code,
                'position' => $parsed['position'] ?? null,
                'status' => $statuses[$tree->id] ?? 'normal',
            ];

            if ($parsed === null) {
                $otherTrees[] = $entry;
            } else {
                $rows[$parsed['row']][] = $entry;
            }
        }

        uksort($rows, fn (string $a, string $b): int => [strlen($a), $a] <=> [strlen($b), $b]);

        $layout = [];

        foreach ($rows as $row => $rowTrees) {
            usort($rowTrees, fn (array $a, array $b): int => [$a['position'], $a['code']] <=> [$b['position'], $b['code']]);
            $layout[] = ['row' => (string) $row, 'trees' => $rowTrees];
        }

        if ($otherTrees !== []) {
            usort($otherTrees, fn (array $a, array $b): int => strcmp($a['code'], $b['code']));
            $layout[] = ['row' => null, 'trees' => $otherTrees];
        }

        return $layout;
    }

    /**
     * @param  array<int, array{row: string|null, trees: array<int, array{status: 'due'|'issue'|'normal'}>}>  $layout
     * @return array{due: int, issue: int, normal: int}
     */
    public static function statusCounts(array $layout): array
    {
        $counts = ['due' => 0, 'issue' => 0, 'normal' => 0];

        foreach ($layout as $row) {
            foreach ($row['trees'] as $tree) {
                $counts[$tree['status']]++;
            }
        }

        return $counts;
    }
}
