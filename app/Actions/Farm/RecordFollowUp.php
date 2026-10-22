<?php

namespace App\Actions\Farm;

use App\Enums\FollowUpOutcome;
use App\Models\Observation;
use App\Models\TreeIssue;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordFollowUp
{
    public function __construct(private StoreObservationPhotos $storePhotos) {}

    /**
     * Record a follow-up check of an open issue: resolved closes it, not resolved schedules the next check.
     *
     * @param  array{observed_on: string|DateTimeInterface, outcome: string|FollowUpOutcome, notes?: string|null, next_follow_up_on?: string|DateTimeInterface|null}  $data
     * @param  array<int, UploadedFile>  $photos
     *
     * @throws ValidationException
     */
    public function handle(TreeIssue $issue, array $data, array $photos = []): Observation
    {
        return DB::transaction(function () use ($issue, $data, $photos): Observation {
            $lockedIssue = TreeIssue::query()->with('tree')->lockForUpdate()->findOrFail($issue->id);

            if ($lockedIssue->tree->isRemoved()) {
                throw ValidationException::withMessages([
                    'tree' => 'ต้นไม้นี้ไม่อยู่แล้ว บันทึกการดูซ้ำไม่ได้',
                ]);
            }

            if (! $lockedIssue->isOpen()) {
                throw ValidationException::withMessages([
                    'issue' => 'ปัญหานี้ปิดไปแล้ว บันทึกการดูซ้ำไม่ได้',
                ]);
            }

            $outcome = $data['outcome'] instanceof FollowUpOutcome
                ? $data['outcome']
                : FollowUpOutcome::from($data['outcome']);
            $observedOn = Carbon::parse($data['observed_on'])->startOfDay();

            $observation = $lockedIssue->observations()->create([
                'tree_id' => $lockedIssue->tree_id,
                'finding_id' => $lockedIssue->finding_id,
                'observed_on' => $observedOn,
                'outcome' => $outcome,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($outcome === FollowUpOutcome::Resolved) {
                $lockedIssue->update(['resolved_on' => $observedOn]);
            } else {
                $nextFollowUpOn = $data['next_follow_up_on'] ?? null;

                $lockedIssue->update([
                    'follow_up_on' => $nextFollowUpOn !== null
                        ? Carbon::parse($nextFollowUpOn)->startOfDay()
                        : $observedOn->copy()->addDays(TreeIssue::FOLLOW_UP_DAYS),
                ]);
            }

            $this->storePhotos->handle($observation, $photos);

            return $observation;
        });
    }
}
