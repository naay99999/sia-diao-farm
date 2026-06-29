<?php

namespace App\Models;

use App\Enums\ExpenseScope;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $expense_category_id
 * @property string $amount
 * @property Carbon $spent_on
 * @property string|null $description
 * @property int|null $crop_cycle_id
 * @property int|null $activity_id
 * @property int $recorded_by
 * @property ExpenseScope $scope
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['expense_category_id', 'amount', 'spent_on', 'description', 'crop_cycle_id', 'activity_id', 'recorded_by'])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /**
     * @return BelongsTo<CropCycle, $this>
     */
    public function cropCycle(): BelongsTo
    {
        return $this->belongsTo(CropCycle::class);
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return Attribute<ExpenseScope::Direct|ExpenseScope::Overhead, never>
     */
    protected function scope(): Attribute
    {
        return Attribute::get(
            fn (): ExpenseScope => $this->crop_cycle_id === null
                ? ExpenseScope::Overhead
                : ExpenseScope::Direct
        );
    }

    /**
     * @param  Builder<Expense>  $query
     */
    public function scopeDirect(Builder $query): void
    {
        $query->whereNotNull('crop_cycle_id');
    }

    /**
     * @param  Builder<Expense>  $query
     */
    public function scopeOverhead(Builder $query): void
    {
        $query->whereNull('crop_cycle_id');
    }
}
