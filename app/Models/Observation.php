<?php

namespace App\Models;

use App\Enums\FollowUpOutcome;
use App\Enums\Severity;
use App\Models\Concerns\TracksProvenance;
use Database\Factories\ObservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A sighting on one tree. A row with an outcome is a follow-up check of its issue.
 *
 * @property int $id
 * @property int $tree_id
 * @property int $finding_id
 * @property int|null $tree_issue_id
 * @property Carbon $observed_on
 * @property Severity|null $severity
 * @property FollowUpOutcome|null $outcome
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property string $created_via
 * @property string $updated_via
 * @property-read Tree $tree
 * @property-read Finding $finding
 * @property-read TreeIssue|null $issue
 */
#[Fillable(['tree_id', 'finding_id', 'tree_issue_id', 'observed_on', 'severity', 'outcome', 'notes'])]
class Observation extends Model
{
    /** @use HasFactory<ObservationFactory> */
    use HasFactory;

    use SoftDeletes;
    use TracksProvenance;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observed_on' => 'date',
            'severity' => Severity::class,
            'outcome' => FollowUpOutcome::class,
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
     * @return BelongsTo<TreeIssue, $this>
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(TreeIssue::class, 'tree_issue_id');
    }

    /**
     * @return HasMany<ObservationPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ObservationPhoto::class);
    }
}
