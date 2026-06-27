<?php

namespace App\Models;

use Database\Factories\FruitTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
class FruitType extends Model
{
    /** @use HasFactory<FruitTypeFactory> */
    use HasFactory;

    /**
     * @return HasMany<FruitVariety, $this>
     */
    public function varieties(): HasMany
    {
        return $this->hasMany(FruitVariety::class);
    }
}
