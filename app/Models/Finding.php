<?php

namespace App\Models;

use App\Enums\FindingKind;
use Database\Factories\FindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property FindingKind $kind
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
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
     * Find a finding by its (trimmed) name, creating it with the given kind when it does not exist yet.
     */
    public static function resolveByName(string $name, FindingKind $kind): Finding
    {
        return static::query()->firstOrCreate(['name' => trim($name)], ['kind' => $kind]);
    }
}
