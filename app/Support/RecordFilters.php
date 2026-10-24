<?php

namespace App\Support;

use App\Models\Season;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RecordFilters
{
    /**
     * Narrow a record query by the filters the records pages share.
     *
     * Pass `$orchardColumn` when the table has its own `orchard_id`, or `$orchardRelation` (`tree`, `season`)
     * when the orchard is reached through a relation. A `season` relation filters by `season_id` directly.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, array $filters, string $dateColumn, ?string $orchardColumn, ?string $orchardRelation = null): Builder
    {
        $orchardId = $filters['orchard_id'] ?? null;
        $seasonId = $filters['season_id'] ?? null;

        if ($seasonId !== null && $orchardRelation === 'season') {
            $query->where('season_id', $seasonId);
        } elseif ($seasonId !== null) {
            $season = Season::query()->findOrFail((int) $seasonId);

            self::applyOrchard($query, $season->orchard_id, $orchardColumn, $orchardRelation);
            $season->applyDateWindow($query, $dateColumn);
        }

        if ($orchardId !== null) {
            self::applyOrchard($query, (int) $orchardId, $orchardColumn, $orchardRelation);
        }

        return $query
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate($dateColumn, '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->whereDate($dateColumn, '<=', $to))
            ->when($filters['created_by'] ?? null, fn (Builder $q, int|string $userId) => $q->where('created_by', $userId))
            ->when($filters['via'] ?? null, fn (Builder $q, string $via) => $q->where('created_via', $via));
    }

    /**
     * @param  Builder<covariant Model>  $query
     */
    private static function applyOrchard(Builder $query, int $orchardId, ?string $orchardColumn, ?string $orchardRelation): void
    {
        if ($orchardColumn !== null) {
            $query->where($orchardColumn, $orchardId);
        } elseif ($orchardRelation !== null) {
            $query->whereHas($orchardRelation, fn (Builder $q) => $q->where('orchard_id', $orchardId));
        }
    }
}
