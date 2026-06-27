<?php

namespace App\Models;

use Database\Factories\FruitVarietyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $fruit_type_id
 * @property string $name
 * @property int $days_to_harvest
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['fruit_type_id', 'name', 'days_to_harvest'])]
class FruitVariety extends Model
{
    /** @use HasFactory<FruitVarietyFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<FruitType, $this>
     */
    public function fruitType(): BelongsTo
    {
        return $this->belongsTo(FruitType::class);
    }

    /**
     * @return HasMany<Plot, $this>
     */
    public function plots(): HasMany
    {
        return $this->hasMany(Plot::class);
    }
}
