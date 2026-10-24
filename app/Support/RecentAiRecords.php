<?php

namespace App\Support;

use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\FarmNote;
use App\Models\Harvest;
use App\Models\Observation;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The latest records an AI client wrote through MCP, newest first, so the owner can see the connection at work.
 */
class RecentAiRecords
{
    /**
     * @return array<int, array{kind:string, title:string, subject:string, recordedOn:string, href:string|null, by:string|null}>
     */
    public static function latest(int $limit = 5): array
    {
        $rows = collect()
            ->merge(self::fetch(CareRecord::query(), $limit))
            ->merge(self::fetch(Harvest::query(), $limit))
            ->merge(self::fetch(Observation::query(), $limit))
            ->merge(self::fetch(Sale::query(), $limit))
            ->merge(self::fetch(Expense::query(), $limit))
            ->merge(self::fetch(FarmNote::query(), $limit));

        return $rows
            ->sortByDesc('createdAt')
            ->take($limit)
            ->map(fn (array $row): array => [
                'kind' => $row['kind'],
                'title' => $row['title'],
                'subject' => $row['subject'],
                'recordedOn' => $row['recordedOn'],
                'href' => $row['href'],
                'by' => $row['by'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Builder<CareRecord|Harvest|Observation|Sale|Expense|FarmNote>  $query
     * @return Collection<int, array{kind:string, title:string, subject:string, recordedOn:string, href:string|null, by:string|null, createdAt:string}>
     */
    private static function fetch(Builder $query, int $limit): Collection
    {
        return $query
            ->with([...RecordSummary::relations($query->getModel()), 'creator:id,name'])
            ->latestFromMcp($limit)
            ->get()
            ->map(fn (CareRecord|Harvest|Observation|Sale|Expense|FarmNote $record): array => [
                ...RecordSummary::for($record),
                'recordedOn' => $record->created_at?->toDateString() ?? '',
                'by' => $record->creator?->name,
                'createdAt' => $record->created_at?->toDateTimeString() ?? '',
            ]);
    }
}
