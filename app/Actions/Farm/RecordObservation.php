<?php

namespace App\Actions\Farm;

use App\Enums\FindingKind;
use App\Enums\Severity;
use App\Models\Finding;
use App\Models\Observation;
use App\Models\Tree;
use App\Models\TreeIssue;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordObservation
{
    public function __construct(private StoreObservationPhotos $storePhotos) {}

    /**
     * Record what was seen on one tree. A problem is appended to the tree's open issue of the
     * same finding, or opens a new one; a growth stage never opens an issue.
     *
     * @param  array{observed_on: string|DateTimeInterface, severity?: Severity|string|null, notes?: string|null, follow_up_on?: string|DateTimeInterface|null}  $data
     * @param  array<int, UploadedFile>  $photos
     *
     * @throws ValidationException
     */
    public function handle(Tree $tree, Finding $finding, array $data, array $photos = []): Observation
    {
        return DB::transaction(function () use ($tree, $finding, $data, $photos): Observation {
            $lockedTree = Tree::query()->lockForUpdate()->findOrFail($tree->id);

            if ($lockedTree->isRemoved()) {
                throw ValidationException::withMessages([
                    'tree' => 'ต้นไม้นี้ไม่อยู่แล้ว บันทึกสภาพต้นไม่ได้',
                ]);
            }

            $observedOn = Carbon::parse($data['observed_on'])->startOfDay();
            $issue = $finding->kind === FindingKind::Problem
                ? $this->openOrAppendIssue($lockedTree, $finding, $observedOn, $data['follow_up_on'] ?? null)
                : null;

            $observation = $lockedTree->observations()->create([
                'finding_id' => $finding->id,
                'tree_issue_id' => $issue?->id,
                'observed_on' => $observedOn,
                'severity' => $data['severity'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->storePhotos->handle($observation, $photos);

            return $observation;
        });
    }

    private function openOrAppendIssue(Tree $tree, Finding $finding, Carbon $observedOn, mixed $followUpOn): TreeIssue
    {
        $issue = $tree->issues()->open()->where('finding_id', $finding->id)->first();
        $followUpDate = $followUpOn !== null ? Carbon::parse($followUpOn)->startOfDay() : null;

        if ($issue === null) {
            return $tree->issues()->create([
                'finding_id' => $finding->id,
                'opened_on' => $observedOn,
                'follow_up_on' => $followUpDate ?? $observedOn->copy()->addDays(TreeIssue::FOLLOW_UP_DAYS),
            ]);
        }

        if ($followUpDate !== null) {
            $issue->update(['follow_up_on' => $followUpDate]);
        }

        return $issue;
    }
}
