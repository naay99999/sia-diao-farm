<?php

namespace App\Actions\Farm;

use App\Enums\CareType;
use App\Models\CareRecord;
use App\Models\Orchard;
use App\Models\Tree;
use App\Models\TreeIssue;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SaveCareRecord
{
    /**
     * Create or update a care record together with its trees and the tree issues it treats.
     *
     * Only issues that sit on one of the record's trees can be linked, and a new link must point at an
     * open issue. A newly linked issue is due for a check again FOLLOW_UP_DAYS after the care date;
     * issues that were already linked or that get unlinked keep their follow-up date. When `issue_ids`
     * is absent, a new record links nothing and an existing record keeps its current links (minus the
     * issues of trees taken off the record).
     *
     * @param  array{type: CareType|string, performed_on: string|DateTimeInterface, product?: string|null, quantity?: string|null, notes?: string|null, tree_ids: array<int, int|string>, issue_ids?: array<int, int|string>|null}  $data
     *
     * @throws ValidationException
     */
    public function handle(Orchard $orchard, array $data, ?CareRecord $record = null): CareRecord
    {
        if ($record !== null && $record->orchard_id !== $orchard->id) {
            throw new InvalidArgumentException('The care record does not belong to the given orchard.');
        }

        return DB::transaction(function () use ($orchard, $data, $record): CareRecord {
            $treeIds = $this->uniqueIds($data['tree_ids']);
            $attributes = Arr::only($data, ['type', 'performed_on', 'product', 'quantity', 'notes']);

            if ($record !== null) {
                $record = CareRecord::query()->lockForUpdate()->findOrFail($record->id);
            }

            $this->assertTreesAreAvailable($orchard, $treeIds, $record);

            if ($record === null) {
                $record = $orchard->careRecords()->create($attributes);
            } else {
                $record->update($attributes);
            }

            $record->trees()->sync($treeIds);
            $this->syncIssues($record, $treeIds, isset($data['issue_ids']) ? $this->uniqueIds($data['issue_ids']) : null);

            return $record;
        });
    }

    /**
     * Issue ids a care record of this type should link by default: the open issues on the given trees
     * for a pesticide treatment, nothing for any other type.
     *
     * @param  array<int, int|string>  $treeIds
     * @return array<int, int>
     */
    public function defaultIssueIds(CareType $type, array $treeIds): array
    {
        if ($type !== CareType::Pesticide || $treeIds === []) {
            return [];
        }

        return TreeIssue::query()
            ->open()
            ->whereIn('tree_id', $treeIds)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $treeIds
     *
     * @throws ValidationException
     */
    private function assertTreesAreAvailable(Orchard $orchard, array $treeIds, ?CareRecord $record): void
    {
        if ($treeIds === []) {
            throw ValidationException::withMessages([
                'tree_ids' => 'ต้องเลือกต้นไม้อย่างน้อย 1 ต้น',
            ]);
        }

        $attachedIds = $record !== null ? $record->trees()->pluck('trees.id')->all() : [];

        $validIds = Tree::query()
            ->where('orchard_id', $orchard->id)
            ->where(fn ($query) => $query->whereNull('removed_on')->orWhereIn('id', $attachedIds))
            ->whereIn('id', $treeIds)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $errors = [];

        foreach ($treeIds as $index => $id) {
            if (! in_array($id, $validIds, true)) {
                $errors["tree_ids.$index"] = 'ต้นไม้ไม่อยู่ในสวนนี้หรือไม่อยู่แล้ว';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, int>  $treeIds
     * @param  array<int, int>|null  $requestedIssueIds
     *
     * @throws ValidationException
     */
    private function syncIssues(CareRecord $record, array $treeIds, ?array $requestedIssueIds): void
    {
        /** @var array<int, int> $linkedIds */
        $linkedIds = $record->issues()->withTrashed()->pluck('tree_issues.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if ($requestedIssueIds === null) {
            $requestedIssueIds = TreeIssue::query()
                ->withTrashed()
                ->whereIn('id', $linkedIds)
                ->whereIn('tree_id', $treeIds)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
        }

        $issues = TreeIssue::query()
            ->whereIn('id', $requestedIssueIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($requestedIssueIds as $issueId) {
            $issue = $issues->get($issueId);
            $alreadyLinked = in_array($issueId, $linkedIds, true);

            if ($issue === null && $alreadyLinked) {
                continue;
            }

            if ($issue === null || ! in_array($issue->tree_id, $treeIds, true)) {
                throw ValidationException::withMessages([
                    'issue_ids' => 'ผูกได้เฉพาะปัญหาของต้นไม้ที่เลือกในบันทึกนี้',
                ]);
            }

            if (! $alreadyLinked && ! $issue->isOpen()) {
                throw ValidationException::withMessages([
                    'issue_ids' => 'ปัญหานี้ปิดไปแล้ว ผูกกับการดูแลใหม่ไม่ได้',
                ]);
            }
        }

        $record->issues()->sync($requestedIssueIds);

        $followUpOn = Carbon::parse($record->performed_on)->startOfDay()->addDays(TreeIssue::FOLLOW_UP_DAYS);

        foreach (array_diff($requestedIssueIds, $linkedIds) as $issueId) {
            $issues->get($issueId)?->update(['follow_up_on' => $followUpOn]);
        }
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, int>
     */
    private function uniqueIds(array $ids): array
    {
        return array_values(array_unique(array_map(intval(...), $ids)));
    }
}
