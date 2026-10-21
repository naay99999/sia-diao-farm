<?php

namespace App\Models;

use App\Models\Concerns\TracksProvenance;
use Database\Factories\HarvestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tree_id
 * @property Carbon $harvested_on
 * @property string $weight_kg
 * @property int|null $fruit_count
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property string $created_via
 * @property string $updated_via
 * @property-read Tree $tree
 */
#[Fillable(['tree_id', 'harvested_on', 'weight_kg', 'fruit_count', 'notes'])]
class Harvest extends Model
{
    /** @use HasFactory<HarvestFactory> */
    use HasFactory;

    use SoftDeletes;
    use TracksProvenance;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'harvested_on' => 'date',
            'weight_kg' => 'decimal:2',
            'fruit_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Tree, $this>
     */
    public function tree(): BelongsTo
    {
        return $this->belongsTo(Tree::class);
    }
}
