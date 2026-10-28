<?php

namespace App\Models;

use App\Enums\FindingKind;
use Database\Factories\FindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property FindingKind $kind
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read bool|null $in_use_by_observations
 * @property-read bool|null $in_use_by_issues
 * @property-read bool|null $in_use_by_notes
 */
#[Fillable(['name', 'kind'])]
class Finding extends Model
{
    /** @use HasFactory<FindingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => FindingKind::class,
        ];
    }

    /**
     * @return HasMany<Observation, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }

    /**
     * @return HasMany<TreeIssue, $this>
     */
    public function treeIssues(): HasMany
    {
        return $this->hasMany(TreeIssue::class);
    }

    /**
     * @return HasMany<FarmNote, $this>
     */
    public function farmNotes(): HasMany
    {
        return $this->hasMany(FarmNote::class);
    }

    /**
     * Whether any observation, issue or knowledge note, trashed ones included, still points at this finding.
     */
    public function isInUse(): bool
    {
        return $this->observations()->withTrashed()->exists()
            || $this->treeIssues()->withTrashed()->exists()
            || $this->farmNotes()->withTrashed()->exists();
    }

    /**
     * Find a finding by its (trimmed) name, creating it with the given kind when it does not exist yet.
     */
    public static function resolveByName(string $name, FindingKind $kind): Finding
    {
        return static::query()->firstOrCreate(['name' => trim($name)], ['kind' => $kind]);
    }
}
