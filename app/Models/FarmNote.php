<?php

namespace App\Models;

use App\Models\Concerns\TracksProvenance;
use Database\Factories\FarmNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Knowledge the farm keeps for later advice. A note may be tied to a finding (a problem or growth stage),
 * to one tree, to both, or to neither (a general note).
 *
 * @property int $id
 * @property string $body
 * @property int|null $finding_id
 * @property int|null $tree_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property string $created_via
 * @property string $updated_via
 * @property-read Finding|null $finding
 * @property-read Tree|null $tree
 */
#[Fillable(['body', 'finding_id', 'tree_id'])]
class FarmNote extends Model
{
    /** @use HasFactory<FarmNoteFactory> */
    use HasFactory;

    use SoftDeletes;
    use TracksProvenance;

    /**
     * @return BelongsTo<Finding, $this>
     */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    /**
     * @return BelongsTo<Tree, $this>
     */
    public function tree(): BelongsTo
    {
        return $this->belongsTo(Tree::class);
    }
}
