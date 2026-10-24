<?php

namespace App\Support;

use App\Models\Orchard;
use App\Models\Tree;
use App\Models\TreeIssue;
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
        $trees = Tree::query()->active()->where('orchard_id', $orchard->id)->get(['id', 'code']);

        $today = Date::today()->toDateString();
        $dueTreeIds = [];
        $issueTreeIds = [];

        TreeIssue::query()->open()->whereIn('tree_id', $trees->pluck('id'))->get(['tree_id', 'follow_up_on'])
            ->each(function (TreeIssue $issue) use ($today, &$dueTreeIds, &$issueTreeIds): void {
                $issueTreeIds[$issue->tree_id] = true;

                if ($issue->follow_up_on->toDateString() <= $today) {
                    $dueTreeIds[$issue->tree_id] = true;
                }
            });

        $rows = [];
        $otherTrees = [];

        foreach ($trees as $tree) {
            $parsed = self::parseCode($tree->code);
            $entry = [
                'id' => $tree->id,
                'code' => $tree->code,
                'position' => $parsed['position'] ?? null,
                'status' => isset($dueTreeIds[$tree->id]) ? 'due' : (isset($issueTreeIds[$tree->id]) ? 'issue' : 'normal'),
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
