import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { careTypeLabels } from '@/types/farm';
import type { CareRecord, CareType, Tree } from '@/types/farm';

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
}: {
    record?: CareRecord;
    defaultType?: CareType;
    errors: Errors;
}) {
    const [type, setType] = useState<CareType>(record?.type ?? defaultType);

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
                                onClick={() => setType(value)}
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
}: {
    trees: Pick<Tree, 'id' | 'code'>[];
    attachedTrees?: Pick<Tree, 'id' | 'code' | 'removed_on'>[];
    initialSelected: number[];
    errors: Errors;
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

    const toggle = (id: number) =>
        setSelected((current) => {
            const next = new Set(current);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });

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
                            setSelected(new Set(trees.map((tree) => tree.id)))
                        }
                    >
                        เลือกทั้งหมด
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setSelected(new Set())}
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
