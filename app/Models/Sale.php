<?php

namespace App\Models;

use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $orchard_id
 * @property Carbon $sold_on
 * @property string|null $buyer_name
 * @property string $weight_kg
 * @property string $amount
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Orchard $orchard
 */
#[Fillable(['orchard_id', 'sold_on', 'buyer_name', 'weight_kg', 'amount', 'notes'])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sold_on' => 'date',
            'weight_kg' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Orchard, $this>
     */
    public function orchard(): BelongsTo
    {
        return $this->belongsTo(Orchard::class);
    }
}
