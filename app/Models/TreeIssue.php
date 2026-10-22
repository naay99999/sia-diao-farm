<?php

namespace App\Models;

use App\Models\Concerns\TracksProvenance;
use Carbon\CarbonInterface;
use Database\Factories\TreeIssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tree_id
 * @property int $finding_id
 * @property Carbon $opened_on
 * @property Carbon $follow_up_on
 * @property Carbon|null $resolved_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property string $created_via
 * @property string $updated_via
 * @property-read Tree $tree
 * @property-read Finding $finding
 */
#[Fillable(['tree_id', 'finding_id', 'opened_on', 'follow_up_on', 'resolved_on'])]
class TreeIssue extends Model
{
    /** @use HasFactory<TreeIssueFactory> */
    use HasFactory;

    use SoftDeletes;
    use TracksProvenance;

    /** Default number of days between a sighting (or treatment) and the next check. */
    public const FOLLOW_UP_DAYS = 7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_on' => 'date',
            'follow_up_on' => 'date',
            'resolved_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Tree, $this>
     */
    public function tree(): BelongsTo
    {
        return $this->belongsTo(Tree::class);
    }

    /**
     * @return BelongsTo<Finding, $this>
     */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    /**
     * @return HasMany<Observation, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }

    /**
     * @return BelongsToMany<CareRecord, $this>
     */
    public function careRecords(): BelongsToMany
    {
        return $this->belongsToMany(CareRecord::class, 'care_record_tree_issue');
    }

    /**
     * @param  Builder<TreeIssue>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('resolved_on');
    }

    /**
     * Open issues whose follow-up date has arrived, on trees that are still standing.
     *
     * @param  Builder<TreeIssue>  $query
     */
    public function scopeDue(Builder $query, CarbonInterface $today): void
    {
        $query->open()
            ->whereDate('follow_up_on', '<=', $today->toDateString())
            ->whereIn('tree_id', Tree::query()->active()->select('id'));
    }

    public function isOpen(): bool
    {
        return $this->resolved_on === null;
    }
}
