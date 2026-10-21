<?php

namespace App\Mcp\Support;

use App\Models\CareRecord;
use App\Models\Expense;
use App\Models\Harvest;
use App\Models\Observation;
use App\Models\Sale;
use App\Models\TreeIssue;
use Illuminate\Database\Eloquent\Model;

/**
 * The one place that turns a farm record into the array shape MCP tools return.
 */
class RecordSerializer
{
    /**
     * @return array<string, mixed>
     */
    public static function serialize(Model $record, bool $includeDeleted = false): array
    {
        self::loadRelations($record);

        $data = match (true) {
            $record instanceof CareRecord => [
                'id' => $record->id,
                'orchard_id' => $record->orchard_id,
                'type' => $record->type->value,
                'performed_on' => $record->performed_on->toDateString(),
                'product' => $record->product,
                'quantity' => $record->quantity,
                'notes' => $record->notes,
                'tree_codes' => $record->trees->sortBy('code')->pluck('code')->values()->all(),
                'issue_ids' => $record->issues->pluck('id')->sort()->values()->all(),
            ],
            $record instanceof Harvest => [
                'id' => $record->id,
                'tree_id' => $record->tree_id,
                'tree_code' => $record->tree->code,
                'harvested_on' => $record->harvested_on->toDateString(),
                'weight_kg' => (float) $record->weight_kg,
                'fruit_count' => $record->fruit_count,
                'notes' => $record->notes,
            ],
            $record instanceof Sale => [
                'id' => $record->id,
                'orchard_id' => $record->orchard_id,
                'sold_on' => $record->sold_on->toDateString(),
                'buyer_name' => $record->buyer_name,
                'weight_kg' => (float) $record->weight_kg,
                'amount' => (float) $record->amount,
                'notes' => $record->notes,
            ],
            $record instanceof Expense => [
                'id' => $record->id,
                'season_id' => $record->season_id,
                'category' => $record->category->value,
                'spent_on' => $record->spent_on->toDateString(),
                'amount' => (float) $record->amount,
                'notes' => $record->notes,
            ],
            $record instanceof Observation => [
                'id' => $record->id,
                'tree_id' => $record->tree_id,
                'tree_code' => $record->tree->code,
                'finding_id' => $record->finding_id,
                'finding' => $record->finding->name,
                'tree_issue_id' => $record->tree_issue_id,
                'observed_on' => $record->observed_on->toDateString(),
                'severity' => $record->severity?->value,
                'outcome' => $record->outcome?->value,
                'notes' => $record->notes,
            ],
            $record instanceof TreeIssue => [
                'id' => $record->id,
                'tree_id' => $record->tree_id,
                'tree_code' => $record->tree->code,
                'finding_id' => $record->finding_id,
                'finding' => $record->finding->name,
                'opened_on' => $record->opened_on->toDateString(),
                'follow_up_on' => $record->follow_up_on->toDateString(),
                'resolved_on' => $record->resolved_on?->toDateString(),
            ],
            default => ['id' => $record->getKey()],
        };

        $data['from_ai'] = method_exists($record, 'isFromAi') ? $record->isFromAi() : false;

        if ($includeDeleted) {
            $data['deleted_at'] = $record->getAttribute('deleted_at')?->toIso8601String();
        }

        return $data;
    }

    private static function loadRelations(Model $record): void
    {
        match (true) {
            $record instanceof CareRecord => $record->loadMissing(['trees:id,code', 'issues:id']),
            $record instanceof Harvest => $record->loadMissing('tree:id,code'),
            $record instanceof Observation, $record instanceof TreeIssue => $record->loadMissing(['tree:id,code', 'finding:id,name']),
            default => null,
        };
    }
}
