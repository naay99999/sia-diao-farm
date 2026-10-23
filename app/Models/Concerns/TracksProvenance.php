<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Support\Provenance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Context;

/**
 * Records who wrote a row and whether it came from the app or from MCP.
 *
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property string $created_via
 * @property string $updated_via
 */
trait TracksProvenance
{
    public static function bootTracksProvenance(): void
    {
        static::creating(function (self $model): void {
            $via = self::currentVia();
            $userId = auth()->user()?->id;

            $model->created_by = $userId;
            $model->updated_by = $userId;
            $model->created_via = $via;
            $model->updated_via = $via;
        });

        static::updating(function (self $model): void {
            $model->updated_by = auth()->user()?->id;
            $model->updated_via = self::currentVia();
        });
    }

    /**
     * The newest rows an AI client created through MCP.
     *
     * @param  Builder<static>  $query
     */
    public function scopeLatestFromMcp(Builder $query, int $limit): void
    {
        $query->where('created_via', Provenance::VIA_MCP)->latest()->latest('id')->limit($limit);
    }

    public function isFromAi(): bool
    {
        return $this->created_via === Provenance::VIA_MCP || $this->updated_via === Provenance::VIA_MCP;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The name of whoever used AI to write or change this row, if anyone did.
     */
    public function aiActorName(): ?string
    {
        return match (true) {
            $this->created_via === Provenance::VIA_MCP => $this->creator?->name,
            $this->updated_via === Provenance::VIA_MCP => $this->updater?->name,
            default => null,
        };
    }

    private static function currentVia(): string
    {
        /** @var string $via */
        $via = Context::get(Provenance::VIA_KEY, Provenance::VIA_APP);

        return $via;
    }
}
