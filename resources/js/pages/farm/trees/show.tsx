import { Form, Head, router, setLayoutProps, usePage } from '@inertiajs/react';
import { MoreVertical } from 'lucide-react';
import { useState } from 'react';
import CareRecordController from '@/actions/App/Http/Controllers/Farm/CareRecordController';
import HarvestController from '@/actions/App/Http/Controllers/Farm/HarvestController';
import TreeController from '@/actions/App/Http/Controllers/Farm/TreeController';
import { useConfirm } from '@/components/confirm-dialog';
import {
    CareRecordFields,
    TreePicker,
} from '@/components/farm/care-record-form';
import { EmptyState } from '@/components/farm/empty-state';
import { HarvestFields } from '@/components/farm/harvest-form';
import { RowActions } from '@/components/farm/row-actions';
import { SubmitButton } from '@/components/farm/submit-button';
import Heading from '@/components/heading';
import TreeFormFields from '@/components/tree-form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatNumber } from '@/lib/format';
import { formatThaiDate } from '@/lib/thai-date';
import { index, show } from '@/routes/orchards';
import { show as showTree } from '@/routes/trees';
import type {
    CareType,
    Orchard,
    SeasonYield,
    TimelineEntry,
    Tree,
} from '@/types/farm';

type TreeWithOrchard = Tree & { orchard: Orchard };

export default function TreeShow({
    tree,
    timeline,
    seasonYields,
    activeTrees,
}: {
    tree: TreeWithOrchard;
    timeline: TimelineEntry[];
    seasonYields: SeasonYield[];
    activeTrees: Pick<Tree, 'id' | 'code'>[];
}) {
    const confirm = useConfirm();
    const [editingEntry, setEditingEntry] = useState<TimelineEntry | null>(
        null,
    );
    const pageErrors =
        usePage<{ errors?: Record<string, string> }>().props.errors ?? {};
    const [harvesting, setHarvesting] = useState(false);
    const [editing, setEditing] = useState(false);
    const [careType, setCareType] = useState<CareType | null>(null);
    const isRemoved = tree.removed_on !== null;

    setLayoutProps({
        breadcrumbs: [
            { title: 'สวน', href: index() },
            { title: tree.orchard.name, href: show(tree.orchard_id) },
            { title: tree.code, href: showTree(tree.id) },
        ],
    });

    return (
        <div className="flex flex-1 flex-col gap-6 px-4 py-5 md:px-6 md:py-6">
            <Head title={tree.code} />
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 space-y-2">
                    <Heading
                        title={tree.code}
                        description={[tree.orchard.name, tree.variety]
                            .filter(Boolean)
                            .join(' · ')}
                    />
                    {isRemoved && (
                        <Badge variant="secondary">
                            ไม่อยู่แล้วตั้งแต่{' '}
                            {formatThaiDate(tree.removed_on!)}
                        </Badge>
                    )}
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="outline"
                            size="icon"
                            className="shrink-0"
                            aria-label="ตัวเลือกของต้นไม้"
                        >
                            <MoreVertical />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="min-w-56">
                        <DropdownMenuItem onSelect={() => setEditing(true)}>
                            แก้ไขข้อมูลต้น
                        </DropdownMenuItem>
                        {isRemoved ? (
                            <DropdownMenuItem
                                onSelect={() =>
                                    router.patch(
                                        TreeController.restore.url(tree.id),
                                    )
                                }
                            >
                                กู้คืนต้นไม้
                            </DropdownMenuItem>
                        ) : (
                            <DropdownMenuItem
                                onSelect={async () => {
                                    if (
                                        await confirm({
                                            title: `บันทึกว่า ${tree.code} ไม่อยู่แล้ว?`,
                                            description:
                                                'ใช้เมื่อต้นตายหรือถูกตัด ประวัติทั้งหมดยังเก็บไว้ และกู้คืนได้ภายหลัง',
                                            confirmLabel:
                                                'บันทึกว่าไม่อยู่แล้ว',
                                        })
                                    ) {
                                        router.patch(
                                            TreeController.remove.url(tree.id),
                                        );
                                    }
                                }}
                            >
                                ต้นตายหรือถูกตัด
                            </DropdownMenuItem>
                        )}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={async () => {
                                if (
                                    await confirm({
                                        title: `ลบ ${tree.code} ถาวร?`,
                                        description:
                                            'ใช้เมื่อเพิ่มต้นผิด ลบได้เฉพาะต้นที่ยังไม่มีบันทึก',
                                        confirmLabel: 'ลบถาวร',
                                        destructive: true,
                                    })
                                ) {
                                    router.delete(
                                        TreeController.destroy.url(tree.id),
                                    );
                                }
                            }}
                        >
                            ลบต้นไม้ถาวร
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            {pageErrors.tree && (
                <p role="alert" className="text-sm text-destructive">
                    {pageErrors.tree}
                </p>
            )}

            {tree.notes && (
                <p className="-mt-3 text-base text-muted-foreground md:text-sm">
                    {tree.notes}
                </p>
            )}

            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <aside className="space-y-6 lg:sticky lg:top-6 lg:col-start-2 lg:row-start-1">
                    {!isRemoved && (
                        <section
                            aria-label="บันทึกงานของต้นนี้"
                            className="grid grid-cols-3 gap-2"
                        >
                            <Button
                                className="col-span-3 h-14 text-base"
                                onClick={() => setHarvesting(true)}
                            >
                                เก็บผลผลิต
                            </Button>
                            <Button
                                variant="outline"
                                className="h-14 text-base"
                                onClick={() => setCareType('fertilizer')}
                            >
                                ใส่ปุ๋ย
                            </Button>
                            <Button
                                variant="outline"
                                className="h-14 text-base"
                                onClick={() => setCareType('pesticide')}
                            >
                                ฉีดยา
                            </Button>
                            <Button
                                variant="outline"
                                className="h-14 text-base"
                                onClick={() => setCareType('other')}
                            >
                                งานอื่น
                            </Button>
                        </section>
                    )}

                    <section className="space-y-3">
                        <h2 className="text-base font-medium">
                            ผลผลิตแต่ละรอบ
                        </h2>
                        {seasonYields.length === 0 ? (
                            <EmptyState>ยังไม่มีรอบการผลิตในสวนนี้</EmptyState>
                        ) : (
                            <ul className="divide-y rounded-lg border bg-card">
                                {seasonYields.map((item) => (
                                    <li
                                        key={item.seasonId}
                                        className="flex items-baseline justify-between gap-3 px-4 py-3"
                                    >
                                        <span className="truncate text-base">
                                            {item.seasonName}
                                        </span>
                                        <span className="shrink-0 text-right">
                                            <span className="text-base font-medium">
                                                {formatNumber(item.weightKg)}
                                            </span>{' '}
                                            <span className="text-sm text-muted-foreground">
                                                กก.
                                            </span>
                                            {item.fruitCount > 0 && (
                                                <span className="block text-sm text-muted-foreground">
                                                    {formatNumber(
                                                        item.fruitCount,
                                                        0,
                                                    )}{' '}
                                                    ผล
                                                </span>
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </aside>

                <section className="min-w-0 space-y-3 lg:col-start-1 lg:row-start-1">
                    <h2 className="text-base font-medium">ประวัติ</h2>
                    {timeline.length === 0 ? (
                        <EmptyState>ยังไม่มีบันทึกของต้นนี้</EmptyState>
                    ) : (
                        <ul className="divide-y rounded-lg border bg-card">
                            {timeline.map((entry) => (
                                <li
                                    key={`${entry.kind}-${entry.id}`}
                                    className="flex items-center gap-3 py-3 pr-3 pl-4"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-base font-medium">
                                            {entry.title}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {formatThaiDate(entry.date)}
                                            {entry.detail &&
                                                ` · ${entry.detail}`}
                                        </p>
                                    </div>
                                    <RowActions
                                        label={`${entry.title} ${formatThaiDate(entry.date)}`}
                                        onEdit={() => setEditingEntry(entry)}
                                        onDelete={async () => {
                                            if (
                                                await confirm({
                                                    title: 'ลบบันทึกนี้?',
                                                    description:
                                                        entry.kind === 'care'
                                                            ? `${entry.title} วันที่ ${formatThaiDate(entry.date)} จะถูกลบจากทุกต้นที่บันทึกไว้ด้วยกัน`
                                                            : `${entry.title} วันที่ ${formatThaiDate(entry.date)}`,
                                                    confirmLabel: 'ลบ',
                                                    destructive: true,
                                                })
                                            ) {
                                                router.delete(
                                                    entry.kind === 'harvest'
                                                        ? HarvestController.destroy.url(
                                                              entry.id,
                                                          )
                                                        : CareRecordController.destroy.url(
                                                              entry.id,
                                                          ),
                                                );
                                            }
                                        }}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <Dialog open={editing} onOpenChange={setEditing}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>แก้ไขข้อมูลต้น</DialogTitle>
                        <DialogDescription>{tree.code}</DialogDescription>
                    </DialogHeader>
                    <Form
                        {...TreeController.update.form(tree.id)}
                        onSuccess={() => setEditing(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <TreeFormFields tree={tree} errors={errors} />
                                <SubmitButton processing={processing} />
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={careType !== null}
                onOpenChange={(open) => !open && setCareType(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>บันทึกการดูแล</DialogTitle>
                        <DialogDescription>{tree.code}</DialogDescription>
                    </DialogHeader>
                    {careType && (
                        <Form
                            key={careType}
                            {...CareRecordController.store.form(
                                tree.orchard_id,
                            )}
                            transform={(data) => ({
                                ...data,
                                tree_ids: [tree.id],
                                return_to: 'tree',
                            })}
                            onSuccess={() => setCareType(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <CareRecordFields
                                        defaultType={careType}
                                        errors={errors}
                                    />
                                    <SubmitButton processing={processing} />
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog open={harvesting} onOpenChange={setHarvesting}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>เก็บผลผลิต</DialogTitle>
                        <DialogDescription>{tree.code}</DialogDescription>
                    </DialogHeader>
                    <Form
                        {...HarvestController.store.form(tree.id)}
                        onSuccess={() => setHarvesting(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <HarvestFields errors={errors} />
                                <SubmitButton processing={processing} />
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editingEntry !== null}
                onOpenChange={(open) => !open && setEditingEntry(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editingEntry?.kind === 'harvest'
                                ? 'แก้ไขผลผลิต'
                                : 'แก้ไขบันทึกการดูแล'}
                        </DialogTitle>
                        <DialogDescription>{tree.code}</DialogDescription>
                    </DialogHeader>
                    {editingEntry?.kind === 'harvest' && (
                        <Form
                            key={`harvest-${editingEntry.id}`}
                            {...HarvestController.update.form(editingEntry.id)}
                            onSuccess={() => setEditingEntry(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <HarvestFields
                                        harvest={editingEntry.record}
                                        errors={errors}
                                    />
                                    <SubmitButton processing={processing} />
                                </>
                            )}
                        </Form>
                    )}
                    {editingEntry?.kind === 'care' && (
                        <Form
                            key={`care-${editingEntry.id}`}
                            {...CareRecordController.update.form(
                                editingEntry.id,
                            )}
                            transform={(data) => ({
                                ...data,
                                return_to: 'tree',
                            })}
                            onSuccess={() => setEditingEntry(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <CareRecordFields
                                        record={editingEntry.record}
                                        errors={errors}
                                    />
                                    <TreePicker
                                        trees={activeTrees}
                                        attachedTrees={
                                            editingEntry.record.trees
                                        }
                                        initialSelected={(
                                            editingEntry.record.trees ?? []
                                        ).map((item) => item.id)}
                                        errors={errors}
                                    />
                                    <SubmitButton processing={processing} />
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
