<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

/**
 * Day-based filters on `date` columns that leave the column bare, so the database can use an index on it.
 *
 * A day's rows sort at or after the day itself (`2026-10-21`) and before the next day, so comparing against
 * `Y-m-d` strings is exact.
 */
class DateFilter
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function onOrAfter(Builder $query, string $column, CarbonInterface|string $day): Builder
    {
        return $query->where($column, '>=', self::day($day)->toDateString());
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function onOrBefore(Builder $query, string $column, CarbonInterface|string $day): Builder
    {
        return $query->where($column, '<', self::day($day)->addDay()->toDateString());
    }

    private static function day(CarbonInterface|string $day): CarbonInterface
    {
        return Date::parse($day instanceof CarbonInterface ? $day->toDateString() : $day)->startOfDay();
    }
}
