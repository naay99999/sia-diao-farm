<?php

namespace App\Models;

use Database\Factories\OrchardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $location
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'location', 'notes'])]
class Orchard extends Model
{
    /** @use HasFactory<OrchardFactory> */
    use HasFactory;

    /**
     * @return HasMany<Tree, $this>
     */
    public function trees(): HasMany
    {
        return $this->hasMany(Tree::class);
    }
}
