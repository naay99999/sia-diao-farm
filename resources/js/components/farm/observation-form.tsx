import { Form } from '@inertiajs/react';
import { useState } from 'react';
import ObservationController from '@/actions/App/Http/Controllers/Farm/ObservationController';
import { todayIso } from '@/components/farm/care-record-form';
import { SubmitButton } from '@/components/farm/submit-button';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { addDaysIso, FOLLOW_UP_DAYS } from '@/lib/thai-date';
import { severityLabels } from '@/types/farm';
import type {
    Finding,
    FindingKind,
    IssueSummary,
    Observation,
    Severity,
    Tree,
} from '@/types/farm';

type Errors = Record<string, string | undefined>;

const MAX_PHOTOS = 3;

const kindLabels: Record<FindingKind, string> = {
    problem: 'ปัญหา',
    stage: 'ระยะการเติบโต',
};

/**
 * Up to three photos per record, straight from the phone camera. Inertia sends them as multipart on its own.
 */
export function PhotoField({ errors }: { errors: Errors }) {
    const [count, setCount] = useState(0);
    const photoError = Object.entries(errors).find(([key]) =>
        key.startsWith('photos'),
    )?.[1];

    return (
        <div className="grid gap-2">
            <Label htmlFor="photos">รูปถ่าย (ไม่เกิน {MAX_PHOTOS} รูป)</Label>
            <Input
                id="photos"
                name="photos[]"
                type="file"
                accept="image/*"
                capture="environment"
                multiple
                className="file:h-auto file:text-base file:font-medium md:file:text-sm"
                onChange={(event) => setCount(event.target.files?.length ?? 0)}
            />
            {count > MAX_PHOTOS && (
                <p
                    role="alert"
                    className="text-base text-destructive md:text-sm"
                >
                    เลือกรูปได้ไม่เกิน {MAX_PHOTOS} รูป
                </p>
            )}
            <InputError message={photoError} />
        </div>
    );
}

/**
 * Three chips; tapping the chosen one again clears it, because severity is optional.
 */
export function SeverityPicker({
    value,
    onChange,
}: {
    value: Severity | null;
    onChange: (value: Severity | null) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label id="severity-label">ความรุนแรง (ไม่บังคับ)</Label>
            <div
                role="group"
                aria-labelledby="severity-label"
                className="grid grid-cols-3 gap-2"
            >
                {(Object.keys(severityLabels) as Severity[]).map((level) => (
                    <Button
                        key={level}
                        type="button"
                        variant={value === level ? 'default' : 'outline'}
                        aria-pressed={value === level}
                        onClick={() => onChange(value === level ? null : level)}
                    >
                        {severityLabels[level]}
                    </Button>
                ))}
            </div>
        </div>
    );
}

export function ObservationForm({
    tree,
    findings,
    openIssues,
    onSuccess,
}: {
    tree: Pick<Tree, 'id'>;
    findings: Finding[];
    openIssues: IssueSummary[];
    onSuccess: () => void;
}) {
    const [kind, setKind] = useState<FindingKind>('problem');
    const [findingId, setFindingId] = useState<number | null>(null);
    const [isOther, setIsOther] = useState(false);
    const [otherName, setOtherName] = useState('');
    const [severity, setSeverity] = useState<Severity | null>(null);
    const [observedOn, setObservedOn] = useState(todayIso);
    const [pickedFollowUpOn, setPickedFollowUpOn] = useState<string | null>(
        null,
    );
    const isProblem = kind === 'problem';
    const followUpOn =
        pickedFollowUpOn ?? addDaysIso(observedOn, FOLLOW_UP_DAYS);
    const chosenName = isOther
        ? otherName.trim()
        : findings.find((finding) => finding.id === findingId)?.name;
    const continuesOpenIssue =
        isProblem &&
        !!chosenName &&
        openIssues.some((issue) => issue.finding === chosenName);

    const selectKind = (next: FindingKind) => {
        setKind(next);
        setFindingId(null);
        setIsOther(false);
        setOtherName('');
        setSeverity(null);
    };

    return (
        <Form
            {...ObservationController.store.form(tree.id)}
            onSuccess={onSuccess}
            className="space-y-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label id="observation-kind-label">ประเภท</Label>
                        <input type="hidden" name="kind" value={kind} />
                        <div
                            role="group"
                            aria-labelledby="observation-kind-label"
                            className="grid grid-cols-2 gap-2"
                        >
                            {(Object.keys(kindLabels) as FindingKind[]).map(
                                (value) => (
                                    <Button
                                        key={value}
                                        type="button"
                                        variant={
                                            kind === value
                                                ? 'default'
                                                : 'outline'
                                        }
                                        aria-pressed={kind === value}
                                        onClick={() => selectKind(value)}
                                    >
                                        {kindLabels[value]}
                                    </Button>
                                ),
                            )}
                        </div>
                        <InputError message={errors.kind} />
                    </div>

                    <div className="grid gap-2">
                        <Label id="finding-label">
                            {isProblem ? 'พบอะไร' : 'ตอนนี้อยู่ระยะไหน'}
                        </Label>
                        {!isOther && findingId !== null && (
                            <input
                                type="hidden"
                                name="finding_id"
                                value={findingId}
                            />
                        )}
                        <div
                            role="group"
                            aria-labelledby="finding-label"
                            className="flex flex-wrap gap-2"
                        >
                            {findings
                                .filter((finding) => finding.kind === kind)
                                .map((finding) => {
                                    const isChosen =
                                        !isOther && findingId === finding.id;

                                    return (
                                        <Button
                                            key={finding.id}
                                            type="button"
                                            variant={
                                                isChosen ? 'default' : 'outline'
                                            }
                                            aria-pressed={isChosen}
                                            onClick={() => {
                                                setIsOther(false);
                                                setFindingId(finding.id);
                                            }}
                                        >
                                            {finding.name}
                                        </Button>
                                    );
                                })}
                            <Button
                                type="button"
                                variant={isOther ? 'default' : 'outline'}
                                aria-pressed={isOther}
                                onClick={() => {
                                    setIsOther(true);
                                    setFindingId(null);
                                }}
                            >
                                อื่น ๆ (พิมพ์ชื่อ)
                            </Button>
                        </div>
                        {isOther && (
                            <Input
                                name="finding_name"
                                value={otherName}
                                onChange={(event) =>
                                    setOtherName(event.target.value)
                                }
                                placeholder={
                                    isProblem ? 'เช่น ใบจุด' : 'เช่น ผลแก่'
                                }
                                aria-label="ชื่อสิ่งที่พบ"
                                autoFocus
                            />
                        )}
                        <InputError
                            message={errors.finding_id ?? errors.finding_name}
                        />
                        {continuesOpenIssue && (
                            <p className="text-base text-muted-foreground md:text-sm">
                                ต้นนี้มีปัญหานี้ค้างอยู่แล้ว
                                ระบบจะบันทึกต่อในเรื่องเดิม
                            </p>
                        )}
                    </div>

                    {isProblem && (
                        <>
                            {severity && (
                                <input
                                    type="hidden"
                                    name="severity"
                                    value={severity}
                                />
                            )}
                            <SeverityPicker
                                value={severity}
                                onChange={setSeverity}
                            />
                            <InputError message={errors.severity} />
                        </>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="observed_on">วันที่พบ</Label>
                        <Input
                            id="observed_on"
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

                    {isProblem && (
                        <div className="grid gap-2">
                            <Label htmlFor="follow_up_on">
                                วันดูซ้ำ (ค่าเริ่มต้น {FOLLOW_UP_DAYS} วัน)
                            </Label>
                            <Input
                                id="follow_up_on"
                                name="follow_up_on"
                                type="date"
                                value={followUpOn}
                                onChange={(event) =>
                                    setPickedFollowUpOn(event.target.value)
                                }
                            />
                            <InputError message={errors.follow_up_on} />
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="observation-notes">หมายเหตุ</Label>
                        <Textarea
                            id="observation-notes"
                            name="notes"
                            rows={3}
                        />
                        <InputError message={errors.notes} />
                    </div>

                    <PhotoField errors={errors} />

                    <SubmitButton processing={processing} />
                </>
            )}
        </Form>
    );
}

/**
 * A saved observation can only change its date, severity and note; anything else means delete and record again.
 */
export function ObservationEditForm({
    observation,
    hasSeverity,
    onSuccess,
}: {
    observation: Observation;
    hasSeverity: boolean;
    onSuccess: () => void;
}) {
    const [severity, setSeverity] = useState<Severity | null>(
        observation.severity,
    );

    return (
        <Form
            {...ObservationController.update.form(observation.id)}
            onSuccess={onSuccess}
            className="space-y-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="edit-observed_on">วันที่พบ</Label>
                        <Input
                            id="edit-observed_on"
                            name="observed_on"
                            type="date"
                            required
                            defaultValue={observation.observed_on.slice(0, 10)}
                        />
                        <InputError message={errors.observed_on} />
                    </div>
                    {hasSeverity && (
                        <>
                            <input
                                type="hidden"
                                name="severity"
                                value={severity ?? ''}
                            />
                            <SeverityPicker
                                value={severity}
                                onChange={setSeverity}
                            />
                            <InputError message={errors.severity} />
                        </>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="edit-observation-notes">หมายเหตุ</Label>
                        <Textarea
                            id="edit-observation-notes"
                            name="notes"
                            rows={3}
                            defaultValue={observation.notes ?? ''}
                        />
                        <InputError message={errors.notes} />
                    </div>
                    <SubmitButton processing={processing} />
                </>
            )}
        </Form>
    );
}
