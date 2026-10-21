<?php

namespace App\Models;

use App\Enums\CareType;
use Database\Factories\CareRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $orchard_id
 * @property CareType $type
 * @property Carbon $performed_on
 * @property string|null $product
 * @property string|null $quantity
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Orchard $orchard
 */
#[Fillable(['orchard_id', 'type', 'performed_on', 'product', 'quantity', 'notes'])]
class CareRecord extends Model
{
    /** @use HasFactory<CareRecordFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CareType::class,
            'performed_on' => 'date',
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
     * @return BelongsToMany<Tree, $this>
     */
    public function trees(): BelongsToMany
    {
        return $this->belongsToMany(Tree::class, 'care_record_tree');
    }
}
