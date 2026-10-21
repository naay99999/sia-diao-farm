<?php

namespace App\Models;

use App\Enums\CareType;
use App\Models\Concerns\TracksProvenance;
use Database\Factories\CareRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
 * @property Carbon|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property string $created_via
 * @property string $updated_via
 * @property-read Orchard $orchard
 */
#[Fillable(['orchard_id', 'type', 'performed_on', 'product', 'quantity', 'notes'])]
class CareRecord extends Model
{
    /** @use HasFactory<CareRecordFactory> */
    use HasFactory;

    use SoftDeletes;
    use TracksProvenance;

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
