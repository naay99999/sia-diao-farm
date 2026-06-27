<?php

namespace App\Models;

use Database\Factories\HarvestItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $harvest_id
 * @property int $grade_id
 * @property string $weight_kg
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['harvest_id', 'grade_id', 'weight_kg'])]
class HarvestItem extends Model
{
    /** @use HasFactory<HarvestItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Harvest, $this>
     */
    public function harvest(): BelongsTo
    {
        return $this->belongsTo(Harvest::class);
    }

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }
}
