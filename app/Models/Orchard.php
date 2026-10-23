<?php

namespace App\Models;

use Database\Factories\OrchardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $location
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'location', 'notes'])]
class Orchard extends Model
{
    /** @use HasFactory<OrchardFactory> */
    use HasFactory;

    /**
     * @return HasMany<Tree, $this>
     */
    public function trees(): HasMany
    {
        return $this->hasMany(Tree::class);
    }

    /**
     * @return HasMany<Season, $this>
     */
    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }

    /**
     * @return HasMany<CareRecord, $this>
     */
    public function careRecords(): HasMany
    {
        return $this->hasMany(CareRecord::class);
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * @return HasOne<Season, $this>
     */
    public function currentSeason(): HasOne
    {
        return $this->hasOne(Season::class)->whereNull('ended_on');
    }

    /**
     * Whether deleting the orchard would wipe farm history, counting soft deleted records too.
     * Trees and seasons that hold nothing yet do not count.
     */
    public function hasRecords(): bool
    {
        $treeIds = $this->trees()->select('id');

        return $this->careRecords()->withTrashed()->exists()
            || $this->sales()->withTrashed()->exists()
            || Expense::withTrashed()->whereIn('season_id', $this->seasons()->select('id'))->exists()
            || Harvest::withTrashed()->whereIn('tree_id', $treeIds)->exists()
            || Observation::withTrashed()->whereIn('tree_id', $treeIds)->exists()
            || TreeIssue::withTrashed()->whereIn('tree_id', $treeIds)->exists();
    }
}
