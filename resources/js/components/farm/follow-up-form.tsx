import { Form } from '@inertiajs/react';
import { useState } from 'react';
import ObservationController from '@/actions/App/Http/Controllers/Farm/ObservationController';
import { todayIso } from '@/components/farm/care-record-form';
import { PhotoField } from '@/components/farm/observation-form';
import { SubmitButton } from '@/components/farm/submit-button';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { addDaysIso, FOLLOW_UP_DAYS } from '@/lib/thai-date';
import type { FollowUpOutcome, IssueSummary } from '@/types/farm';

const outcomeLabels: Record<FollowUpOutcome, string> = {
    resolved: 'หายแล้ว',
    not_resolved: 'ยังไม่หาย',
};

export function FollowUpForm({
    issue,
    onSuccess,
}: {
    issue: IssueSummary;
    onSuccess: () => void;
}) {
    const [outcome, setOutcome] = useState<FollowUpOutcome | null>(null);
    const [observedOn, setObservedOn] = useState(todayIso);
    const [pickedNextOn, setPickedNextOn] = useState<string | null>(null);
    const nextFollowUpOn =
        pickedNextOn ?? addDaysIso(observedOn, FOLLOW_UP_DAYS);

    return (
        <Form
            {...ObservationController.storeFollowUp.form(issue.id)}
            onSuccess={onSuccess}
            className="space-y-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label id="outcome-label">ผลการดูซ้ำ</Label>
                        {outcome && (
                            <input
                                type="hidden"
                                name="outcome"
                                value={outcome}
                            />
                        )}
                        <div
                            role="group"
                            aria-labelledby="outcome-label"
                            className="grid grid-cols-2 gap-2"
                        >
                            {(
                                Object.keys(outcomeLabels) as FollowUpOutcome[]
                            ).map((value) => (
                                <Button
                                    key={value}
                                    type="button"
                                    variant={
                                        outcome === value
                                            ? 'default'
                                            : 'outline'
                                    }
                                    aria-pressed={outcome === value}
                                    onClick={() => setOutcome(value)}
                                >
                                    {outcomeLabels[value]}
                                </Button>
                            ))}
                        </div>
                        <InputError message={errors.outcome} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="follow-up-observed_on">
                            วันที่ดูซ้ำ
                        </Label>
                        <Input
                            id="follow-up-observed_on"
                            name="observed_on"
                            type="date"
                            required
                            value={observedOn}
                            onChange={(event) =>
                                setObservedOn(event.target.value)
                            }
                        />
                        <InputError message={errors.observed_on} />
                    </div>

                    {outcome === 'not_resolved' && (
                        <div className="grid gap-2">
                            <Label htmlFor="next_follow_up_on">
                                วันดูซ้ำครั้งถัดไป
                            </Label>
                            <Input
                                id="next_follow_up_on"
                                name="next_follow_up_on"
                                type="date"
                                value={nextFollowUpOn}
                                onChange={(event) =>
                                    setPickedNextOn(event.target.value)
                                }
                            />
                            <InputError message={errors.next_follow_up_on} />
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="follow-up-notes">หมายเหตุ</Label>
                        <Textarea id="follow-up-notes" name="notes" rows={3} />
                        <InputError message={errors.notes} />
                    </div>

                    <PhotoField errors={errors} />

                    <SubmitButton processing={processing} />
                </>
            )}
        </Form>
    );
}
