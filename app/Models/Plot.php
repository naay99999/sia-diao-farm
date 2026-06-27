<?php

namespace App\Models;

use App\Enums\CropCycleStatus;
use Database\Factories\PlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $fruit_variety_id
 * @property int $tree_count
 * @property Carbon|null $planted_at
 * @property string|null $area_rai
 * @property string|null $notes
 * @property int|null $tree_age_years
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'fruit_variety_id', 'tree_count', 'planted_at', 'area_rai', 'notes'])]
class Plot extends Model
{
    /** @use HasFactory<PlotFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'planted_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<FruitVariety, $this>
     */
    public function fruitVariety(): BelongsTo
    {
        return $this->belongsTo(FruitVariety::class);
    }

    /**
     * @return HasMany<CropCycle, $this>
     */
    public function cropCycles(): HasMany
    {
        return $this->hasMany(CropCycle::class);
    }

    /**
     * @return HasOne<CropCycle, $this>
     */
    public function activeCropCycle(): HasOne
    {
        return $this->hasOne(CropCycle::class)
            ->where('status', CropCycleStatus::Active)
            ->latestOfMany();
    }

    /**
     * @return Attribute<int|null, never>
     */
    protected function treeAgeYears(): Attribute
    {
        return Attribute::get(
            fn (): ?int => $this->planted_at?->diffInYears(now()) !== null
                ? (int) $this->planted_at->diffInYears(now())
                : null
        );
    }
}
