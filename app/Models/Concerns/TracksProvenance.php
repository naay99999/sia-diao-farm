<?php

namespace App\Models\Concerns;

use App\Support\Provenance;
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
            $userId = auth()->id();

            $model->created_by = $userId;
            $model->updated_by = $userId;
            $model->created_via = $via;
            $model->updated_via = $via;
        });

        static::updating(function (self $model): void {
            $model->updated_by = auth()->id();
            $model->updated_via = self::currentVia();
        });
    }

    public function isFromAi(): bool
    {
        return $this->created_via === Provenance::VIA_MCP || $this->updated_via === Provenance::VIA_MCP;
    }

    private static function currentVia(): string
    {
        /** @var string $via */
        $via = Context::get(Provenance::VIA_KEY, Provenance::VIA_APP);

        return $via;
    }
}
