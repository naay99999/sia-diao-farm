<?php

namespace App\Models;

use App\Enums\CropCycleStage;
use App\Enums\CropCycleStatus;
use Database\Factories\CropCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $plot_id
 * @property int $fruit_variety_id
 * @property string $label
 * @property CropCycleStage $stage
 * @property CropCycleStatus $status
 * @property Carbon|null $flowering_date
 * @property Carbon|null $expected_harvest_date
 * @property Carbon $started_at
 * @property Carbon|null $closed_at
 * @property int $recorded_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['plot_id', 'fruit_variety_id', 'label', 'stage', 'status', 'flowering_date', 'expected_harvest_date', 'started_at', 'closed_at', 'recorded_by', 'notes'])]
class CropCycle extends Model
{
    /** @use HasFactory<CropCycleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'stage' => CropCycleStage::class,
            'status' => CropCycleStatus::class,
            'flowering_date' => 'date',
            'expected_harvest_date' => 'date',
            'started_at' => 'date',
            'closed_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Plot, $this>
     */
    public function plot(): BelongsTo
    {
        return $this->belongsTo(Plot::class);
    }

    /**
     * @return BelongsTo<FruitVariety, $this>
     */
    public function fruitVariety(): BelongsTo
    {
        return $this->belongsTo(FruitVariety::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Record the flowering date and forecast the harvest date.
     */
    public function recordFlowering(Carbon $floweringDate): void
    {
        $this->flowering_date = $floweringDate;
        $this->expected_harvest_date = $floweringDate->copy()
            ->addDays($this->fruitVariety->days_to_harvest);
        $this->stage = CropCycleStage::Flowering;
        $this->save();
    }
}
