<?php

namespace App\Models;

use Database\Factories\TreeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $orchard_id
 * @property string $code
 * @property string|null $variety
 * @property Carbon|null $planted_on
 * @property Carbon|null $removed_on
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Orchard $orchard
 */
#[Fillable(['orchard_id', 'code', 'variety', 'planted_on', 'removed_on', 'notes'])]
class Tree extends Model
{
    /** @use HasFactory<TreeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planted_on' => 'date',
            'removed_on' => 'date',
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
     * @param  Builder<Tree>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('removed_on');
    }

    public function isRemoved(): bool
    {
        return $this->removed_on !== null;
    }
}
