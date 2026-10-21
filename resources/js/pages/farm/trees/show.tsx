import { Form, Head, router, setLayoutProps, usePage } from '@inertiajs/react';
import { MoreVertical } from 'lucide-react';
import { useState } from 'react';
import CareRecordController from '@/actions/App/Http/Controllers/Farm/CareRecordController';
import HarvestController from '@/actions/App/Http/Controllers/Farm/HarvestController';
import TreeController from '@/actions/App/Http/Controllers/Farm/TreeController';
import { CareRecordFields, TreePicker } from '@/components/farm/care-record-form';
import { HarvestFields } from '@/components/farm/harvest-form';
import Heading from '@/components/heading';
import TreeFormFields from '@/components/tree-form-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
    const [editingEntry, setEditingEntry] = useState<TimelineEntry | null>(
        null,
    );
    const pageErrors = usePage<{ errors?: Record<string, string> }>().props
        .errors ?? {};
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
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title={tree.code} />
            <div className="flex items-start justify-between gap-2">
                <div className="space-y-2">
                    <Heading
                        title={tree.code}
                        description={[tree.orchard.name, tree.variety].filter(Boolean).join(' · ')}
                    />
                    {isRemoved && <Badge variant="secondary">ไม่อยู่แล้ว</Badge>}
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" size="icon" aria-label="เมนูต้นไม้">
                            <MoreVertical />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onSelect={() => setEditing(true)}>แก้ไข</DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={() => {
                                if (confirm('ลบต้นไม้นี้?')) {
                                    router.delete(TreeController.destroy.url(tree.id));
                                }
                            }}
                        >
                            ลบต้นไม้
                        </DropdownMenuItem>
                        {isRemoved ? (
                            <DropdownMenuItem onSelect={() => router.patch(TreeController.restore.url(tree.id))}>
                                กู้คืนต้นไม้
                            </DropdownMenuItem>
                        ) : (
                            <DropdownMenuItem
                                onSelect={() => {
                                    if (confirm('บันทึกว่าต้นนี้ไม่อยู่แล้ว?')) {
                                        router.patch(TreeController.remove.url(tree.id));
                                    }
                                }}
                            >
                                ต้นไม้ไม่อยู่แล้ว
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <Dialog open={editing} onOpenChange={setEditing}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>แก้ไขต้นไม้</DialogTitle>
                        <DialogDescription>แก้ไขข้อมูลต้นไม้</DialogDescription>
                    </DialogHeader>
                    <Form
                        {...TreeController.update.form(tree.id)}
                        onSuccess={() => setEditing(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <TreeFormFields tree={tree} errors={errors} />
                                <Button type="submit" disabled={processing}>
                                    บันทึก
                                </Button>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            {pageErrors.tree && (
                <p role="alert" className="text-sm text-destructive">
                    {pageErrors.tree}
                </p>
            )}

            {tree.notes && <p className="text-sm">{tree.notes}</p>}

            {!isRemoved && (
                <div className="grid grid-cols-3 gap-2">
                    <Button
                        className="h-14 text-base"
                        onClick={() => setCareType('fertilizer')}
                    >
                        ใส่ปุ๋ย
                    </Button>
                    <Button
                        className="h-14 text-base"
                        onClick={() => setCareType('pesticide')}
                    >
                        ฉีดยา
                    </Button>
                    <Button
                        className="h-14 text-base"
                        onClick={() => setHarvesting(true)}
                    >
                        ผลผลิต
                    </Button>
                </div>
            )}

            <Dialog
                open={careType !== null}
                onOpenChange={(open) => !open && setCareType(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>บันทึกการดูแล</DialogTitle>
                        <DialogDescription>{tree.code}</DialogDescription>
                    </DialogHeader>
                    {careType && (
                        <Form
                            key={careType}
                            {...CareRecordController.store.form(tree.orchard_id)}
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
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="h-12 w-full text-base"
                                    >
                                        บันทึก
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog open={harvesting} onOpenChange={setHarvesting}>
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>บันทึกผลผลิต</DialogTitle>
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
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="h-12 w-full text-base"
                                >
                                    บันทึก
                                </Button>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editingEntry !== null}
                onOpenChange={(open) => !open && setEditingEntry(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto">
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
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="h-12 w-full text-base"
                                    >
                                        บันทึก
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                    {editingEntry?.kind === 'care' && (
                        <Form
                            key={`care-${editingEntry.id}`}
                            {...CareRecordController.update.form(editingEntry.id)}
                            transform={(data) => ({ ...data, return_to: 'tree' })}
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
                                        attachedTrees={editingEntry.record.trees}
                                        initialSelected={(
                                            editingEntry.record.trees ?? []
                                        ).map((item) => item.id)}
                                        errors={errors}
                                    />
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="h-12 w-full text-base"
                                    >
                                        บันทึก
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>

            {seasonYields.length > 0 && (
                <section className="space-y-3">
                    <h2 className="text-lg font-medium">ผลผลิตแต่ละรอบ</h2>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {seasonYields.map((item) => (
                            <div
                                key={item.seasonId}
                                className="rounded-lg border p-3"
                            >
                                <p className="font-medium">{item.seasonName}</p>
                                <p className="text-sm text-muted-foreground">
                                    {item.weightKg.toLocaleString('th-TH')} กก.
                                    {item.fruitCount > 0 &&
                                        ` · ${item.fruitCount.toLocaleString('th-TH')} ผล`}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>
            )}

            <section className="space-y-3">
                <h2 className="text-lg font-medium">ประวัติ</h2>
                {timeline.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        ยังไม่มีบันทึก
                    </p>
                )}
                <div className="grid gap-2">
                    {timeline.map((entry) => (
                        <div
                            key={`${entry.kind}-${entry.id}`}
                            className="flex items-start justify-between gap-2 rounded-lg border p-3"
                        >
                            <div className="min-w-0">
                                <p className="font-medium">{entry.title}</p>
                                <p className="text-sm text-muted-foreground">
                                    {formatThaiDate(entry.date)}
                                    {entry.detail && ` · ${entry.detail}`}
                                </p>
                            </div>
                            <div className="flex shrink-0 gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setEditingEntry(entry)}
                                >
                                    แก้ไข
                                </Button>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => {
                                        if (confirm('ลบบันทึกนี้?')) {
                                            router.delete(
                                                entry.kind === 'harvest'
                                                    ? HarvestController.destroy.url(entry.id)
                                                    : CareRecordController.destroy.url(entry.id),
                                            );
                                        }
                                    }}
                                >
                                    ลบ
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            </section>
        </div>
    );
}
