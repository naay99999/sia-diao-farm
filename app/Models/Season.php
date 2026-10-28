<?php

namespace App\Models;

use App\Support\DateFilter;
use Carbon\CarbonInterface;
use Database\Factories\SeasonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * @property int $id
 * @property int $orchard_id
 * @property string $name
 * @property Carbon $started_on
 * @property Carbon|null $ended_on
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Orchard $orchard
 */
#[Fillable(['orchard_id', 'name', 'started_on', 'ended_on', 'notes'])]
class Season extends Model
{
    /** @use HasFactory<SeasonFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Orchard, $this>
     */
    public function orchard(): BelongsTo
    {
        return $this->belongsTo(Orchard::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function isOpen(): bool
    {
        return $this->ended_on === null;
    }

    public function effectiveEnd(): CarbonInterface
    {
        return $this->ended_on ?? Date::today();
    }

    public function containsDate(CarbonInterface $date): bool
    {
        $day = $date->toDateString();

        return $day >= $this->started_on->toDateString()
            && $day <= $this->effectiveEnd()->toDateString();
    }

    /**
     * @param  Builder<Season>  $query
     */
    public function scopeCovering(Builder $query, int $orchardId, CarbonInterface $date): void
    {
        $isPastOrToday = $date->toDateString() <= Date::today()->toDateString();

        DateFilter::onOrBefore($query->where('orchard_id', $orchardId), 'started_on', $date)
            ->where(function (Builder $inner) use ($date, $isPastOrToday): void {
                DateFilter::onOrAfter($inner, 'ended_on', $date);

                if ($isPastOrToday) {
                    $inner->orWhereNull('ended_on');
                }
            });
    }

    /**
     * Restrict a query to rows whose date column falls inside this season (inclusive).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyDateWindow(Builder $query, string $column): Builder
    {
        return DateFilter::onOrBefore(
            DateFilter::onOrAfter($query, $column, $this->started_on),
            $column,
            $this->effectiveEnd(),
        );
    }
}
