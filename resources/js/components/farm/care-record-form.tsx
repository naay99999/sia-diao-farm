import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatThaiDate } from '@/lib/thai-date';
import { careTypeLabels } from '@/types/farm';
import type { CareRecord, CareType, IssueSummary, Tree } from '@/types/farm';

type Errors = Record<string, string | undefined>;

export function todayIso(): string {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
}

export function CareRecordFields({
    record,
    defaultType = 'fertilizer',
    errors,
    onTypeChange,
}: {
    record?: CareRecord;
    defaultType?: CareType;
    errors: Errors;
    onTypeChange?: (type: CareType) => void;
}) {
    const [type, setType] = useState<CareType>(record?.type ?? defaultType);
    const selectType = (value: CareType) => {
        setType(value);
        onTypeChange?.(value);
    };

    return (
        <>
            <div className="grid gap-2">
                <Label id="care-type-label">ประเภทงาน</Label>
                <input type="hidden" name="type" value={type} />
                <div
                    role="group"
                    aria-labelledby="care-type-label"
                    className="grid grid-cols-3 gap-2"
                >
                    {(Object.keys(careTypeLabels) as CareType[]).map(
                        (value) => (
                            <Button
                                key={value}
                                type="button"
                                variant={type === value ? 'default' : 'outline'}
                                aria-pressed={type === value}
                                onClick={() => selectType(value)}
                            >
                                {careTypeLabels[value]}
                            </Button>
                        ),
                    )}
                </div>
                <InputError message={errors.type} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="performed_on">วันที่ทำ</Label>
                <Input
                    id="performed_on"
                    name="performed_on"
                    type="date"
                    required
                    defaultValue={
                        record?.performed_on.slice(0, 10) ?? todayIso()
                    }
                />
                <InputError message={errors.performed_on} />
            </div>
            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="product">ชื่อปุ๋ย/ยา</Label>
                    <Input
                        id="product"
                        name="product"
                        defaultValue={record?.product ?? ''}
                        placeholder="เช่น 15-15-15"
                    />
                    <InputError message={errors.product} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="quantity">ปริมาณ</Label>
                    <Input
                        id="quantity"
                        name="quantity"
                        defaultValue={record?.quantity ?? ''}
                        placeholder="เช่น 2 กก."
                    />
                    <InputError message={errors.quantity} />
                </div>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="care-notes">หมายเหตุ</Label>
                <Textarea
                    id="care-notes"
                    name="notes"
                    rows={3}
                    defaultValue={record?.notes ?? ''}
                />
                <InputError message={errors.notes} />
            </div>
        </>
    );
}

export function TreePicker({
    trees: activeTrees,
    attachedTrees = [],
    initialSelected,
    errors,
    onSelectionChange,
}: {
    trees: Pick<Tree, 'id' | 'code'>[];
    attachedTrees?: Pick<Tree, 'id' | 'code' | 'removed_on'>[];
    initialSelected: number[];
    errors: Errors;
    onSelectionChange?: (selected: Set<number>) => void;
}) {
    const activeIds = new Set(activeTrees.map((tree) => tree.id));
    const removedAttached = attachedTrees.filter(
        (tree) => tree.removed_on && !activeIds.has(tree.id),
    );
    const trees: (Pick<Tree, 'id' | 'code'> & { removed?: boolean })[] = [
        ...activeTrees,
        ...removedAttached.map((tree) => ({ ...tree, removed: true })),
    ];
    const [selected, setSelected] = useState<Set<number>>(
        () => new Set(initialSelected),
    );
    const treeError = Object.entries(errors).find(([key]) =>
        key.startsWith('tree_ids'),
    )?.[1];

    const select = (next: Set<number>) => {
        setSelected(next);
        onSelectionChange?.(next);
    };

    const toggle = (id: number) => {
        const next = new Set(selected);

        if (next.has(id)) {
            next.delete(id);
        } else {
            next.add(id);
        }

        select(next);
    };

    return (
        <div className="grid gap-2">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <Label>
                    เลือกต้นไม้ {selected.size} จาก {trees.length} ต้น
                </Label>
                <div className="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            select(new Set(trees.map((tree) => tree.id)))
                        }
                    >
                        เลือกทั้งหมด
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => select(new Set())}
                    >
                        ล้างที่เลือก
                    </Button>
                </div>
            </div>
            <div className="grid max-h-80 gap-2 overflow-y-auto sm:grid-cols-2">
                {trees.map((tree) => (
                    <label
                        key={tree.id}
                        className="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border p-3 has-[:checked]:border-primary has-[:checked]:bg-brand-soft"
                    >
                        <input
                            type="checkbox"
                            name="tree_ids[]"
                            value={tree.id}
                            checked={selected.has(tree.id)}
                            onChange={() => toggle(tree.id)}
                            className="size-5 accent-primary"
                        />
                        <span className="font-medium">
                            {tree.code}
                            {tree.removed && (
                                <span className="font-normal text-muted-foreground">
                                    {' '}
                                    (ไม่อยู่แล้ว)
                                </span>
                            )}
                        </span>
                    </label>
                ))}
            </div>
            <InputError message={treeError} />
        </div>
    );
}

/**
 * Checkboxes for the open problems of the selected trees. A pesticide record is the treatment, so it ticks them by default.
 */
function IssueLinks({
    issues,
    ticked,
    onToggle,
}: {
    issues: IssueSummary[];
    ticked: Set<number>;
    onToggle: (id: number) => void;
}) {
    if (issues.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-2">
            <Label>ผูกกับปัญหา</Label>
            <p className="-mt-1 text-base text-muted-foreground md:text-sm">
                ปัญหาที่ผูกใหม่จะเลื่อนวันดูซ้ำเป็น 7 วันหลังวันที่ทำ
            </p>
            <div className="grid gap-2">
                {issues.map((issue) => (
                    <label
                        key={issue.id}
                        className="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border p-3 has-[:checked]:border-primary has-[:checked]:bg-brand-soft"
                    >
                        <input
                            type="checkbox"
                            name="issue_ids[]"
                            value={issue.id}
                            checked={ticked.has(issue.id)}
                            onChange={() => onToggle(issue.id)}
                            className="size-5 accent-primary"
                        />
                        <span className="min-w-0 flex-1">
                            <span className="font-medium">
                                {issue.treeCode} · {issue.finding}
                            </span>
                            <span className="block text-base text-muted-foreground md:text-sm">
                                ดูซ้ำ {formatThaiDate(issue.followUpOn)}
                            </span>
                        </span>
                    </label>
                ))}
            </div>
        </div>
    );
}

/**
 * Everything a care record form holds: the fields, the trees it covers and the problems it treats.
 * It owns the type and the tree selection so the problem ticks can follow both.
 *
 * Pass `trees` for a picker, or leave it out when the page decides the trees (`initialSelected` is then fixed).
 * `openIssues` are the issues the page knows; links on the record that are not among them are sent
 * back untouched, because the form always submits the full set of links.
 */
export function CareRecordFormBody({
    record,
    defaultType = 'fertilizer',
    errors,
    trees,
    attachedTrees,
    initialSelected,
    openIssues,
}: {
    record?: CareRecord;
    defaultType?: CareType;
    errors: Errors;
    trees?: Pick<Tree, 'id' | 'code'>[];
    attachedTrees?: Pick<Tree, 'id' | 'code' | 'removed_on'>[];
    initialSelected: number[];
    openIssues: IssueSummary[];
}) {
    const isEditing = record !== undefined;
    const openIssueIds = new Set(openIssues.map((issue) => issue.id));
    const defaultTicks = (
        careType: CareType,
        treeIds: Set<number>,
    ): Set<number> =>
        careType === 'pesticide'
            ? new Set(
                  openIssues
                      .filter((issue) => treeIds.has(issue.treeId))
                      .map((issue) => issue.id),
              )
            : new Set();

    const [type, setType] = useState<CareType>(record?.type ?? defaultType);
    const [selected, setSelected] = useState<Set<number>>(
        () => new Set(initialSelected),
    );
    const [ticked, setTicked] = useState<Set<number>>(() =>
        record
            ? new Set(
                  (record.issue_ids ?? []).filter((id) => openIssueIds.has(id)),
              )
            : defaultTicks(defaultType, new Set(initialSelected)),
    );
    const issueError = Object.entries(errors).find(([key]) =>
        key.startsWith('issue_ids'),
    )?.[1];
    const keptIssueIds = (record?.issue_ids ?? []).filter(
        (id) => !openIssueIds.has(id),
    );

    const changeType = (next: CareType) => {
        setType(next);

        if (!isEditing) {
            setTicked(defaultTicks(next, selected));
        }
    };

    const changeSelection = (next: Set<number>) => {
        setSelected(next);

        if (!isEditing) {
            setTicked(defaultTicks(type, next));
        }
    };

    const toggleIssue = (id: number) =>
        setTicked((current) => {
            const next = new Set(current);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });

    return (
        <>
            <CareRecordFields
                record={record}
                defaultType={defaultType}
                errors={errors}
                onTypeChange={changeType}
            />
            {trees && (
                <TreePicker
                    trees={trees}
                    attachedTrees={attachedTrees}
                    initialSelected={initialSelected}
                    errors={errors}
                    onSelectionChange={changeSelection}
                />
            )}
            <IssueLinks
                issues={openIssues.filter((issue) =>
                    selected.has(issue.treeId),
                )}
                ticked={ticked}
                onToggle={toggleIssue}
            />
            {keptIssueIds.map((id) => (
                <input key={id} type="hidden" name="issue_ids[]" value={id} />
            ))}
            <InputError message={issueError} />
        </>
    );
}
