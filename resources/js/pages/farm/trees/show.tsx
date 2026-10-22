import { Form, Head, router, setLayoutProps, usePage } from '@inertiajs/react';
import { MoreVertical } from 'lucide-react';
import { useState } from 'react';
import CareRecordController from '@/actions/App/Http/Controllers/Farm/CareRecordController';
import HarvestController from '@/actions/App/Http/Controllers/Farm/HarvestController';
import ObservationController from '@/actions/App/Http/Controllers/Farm/ObservationController';
import TreeController from '@/actions/App/Http/Controllers/Farm/TreeController';
import { useConfirm } from '@/components/confirm-dialog';
import {
    CareRecordFormBody,
    todayIso,
} from '@/components/farm/care-record-form';
import { EmptyState } from '@/components/farm/empty-state';
import { FollowUpForm } from '@/components/farm/follow-up-form';
import { HarvestFields } from '@/components/farm/harvest-form';
import {
    ObservationEditForm,
    ObservationForm,
} from '@/components/farm/observation-form';
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
    Finding,
    IssueSummary,
    LatestStage,
    Orchard,
    SeasonYield,
    TimelineEntry,
    Tree,
} from '@/types/farm';

type TreeWithOrchard = Tree & { orchard: Orchard };

function destroyUrl(entry: TimelineEntry): string {
    switch (entry.kind) {
        case 'harvest':
            return HarvestController.destroy.url(entry.id);
        case 'observation':
            return ObservationController.destroy.url(entry.id);
        case 'care':
            return CareRecordController.destroy.url(entry.id);
    }
}

export default function TreeShow({
    tree,
    timeline,
    seasonYields,
    activeTrees,
    openIssues,
    latestStage,
    findings,
}: {
    tree: TreeWithOrchard;
    timeline: TimelineEntry[];
    seasonYields: SeasonYield[];
    activeTrees: Pick<Tree, 'id' | 'code'>[];
    openIssues: IssueSummary[];
    latestStage: LatestStage | null;
    findings: Finding[];
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
    const [observing, setObserving] = useState(false);
    const [followingUp, setFollowingUp] = useState<IssueSummary | null>(null);
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
                <p
                    role="alert"
                    className="text-base text-destructive md:text-sm"
                >
                    {pageErrors.tree}
                </p>
            )}

            {tree.notes && (
                <p className="-mt-3 text-base text-muted-foreground md:text-sm">
                    {tree.notes}
                </p>
            )}

            {latestStage && (
                <p
                    className={
                        latestStage.inCurrentSeason
                            ? '-mt-3 text-lg md:text-base'
                            : '-mt-3 text-lg text-muted-foreground md:text-base'
                    }
                >
                    ระยะล่าสุด: {latestStage.finding} ·{' '}
                    {formatThaiDate(latestStage.observedOn)}
                </p>
            )}

            {openIssues.length > 0 && (
                <section aria-label="ปัญหาที่ยังไม่จบ" className="space-y-3">
                    <h2 className="text-xl font-semibold md:text-lg">
                        ปัญหาที่ยังไม่จบ
                    </h2>
                    <ul className="divide-y overflow-hidden rounded-lg border bg-card">
                        {openIssues.map((issue) => (
                            <li
                                key={issue.id}
                                className={
                                    issue.isDue
                                        ? 'flex items-center gap-3 bg-brand-soft py-3 pr-3 pl-4'
                                        : 'flex items-center gap-3 py-3 pr-3 pl-4'
                                }
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="flex flex-wrap items-center gap-x-2 text-lg font-medium md:text-base">
                                        {issue.finding}
                                        {issue.isDue && (
                                            <Badge>
                                                {issue.followUpOn < todayIso()
                                                    ? 'เลยกำหนด'
                                                    : 'ถึงกำหนดวันนี้'}
                                            </Badge>
                                        )}
                                    </p>
                                    <p className="text-base text-muted-foreground md:text-sm">
                                        พบเมื่อ {formatThaiDate(issue.openedOn)}{' '}
                                        · ดูซ้ำ{' '}
                                        {formatThaiDate(issue.followUpOn)}
                                    </p>
                                </div>
                                {!isRemoved && (
                                    <Button
                                        variant="outline"
                                        className="shrink-0"
                                        onClick={() => setFollowingUp(issue)}
                                    >
                                        ดูซ้ำ
                                    </Button>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <aside className="space-y-6 lg:sticky lg:top-6 lg:col-start-2 lg:row-start-1">
                    {!isRemoved && (
                        <section
                            aria-label="บันทึกงานของต้นนี้"
                            className="grid grid-cols-3 gap-2"
                        >
                            <Button
                                className="col-span-3"
                                onClick={() => setHarvesting(true)}
                            >
                                เก็บผลผลิต
                            </Button>
                            <Button
                                variant="outline"
                                className="col-span-3"
                                onClick={() => setObserving(true)}
                            >
                                บันทึกสภาพต้น
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => setCareType('fertilizer')}
                            >
                                ใส่ปุ๋ย
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => setCareType('pesticide')}
                            >
                                ฉีดยา
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => setCareType('other')}
                            >
                                งานอื่น
                            </Button>
                        </section>
                    )}

                    <section className="space-y-3">
                        <h2 className="text-xl font-semibold md:text-lg">
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
                                        <span className="truncate text-lg md:text-base">
                                            {item.seasonName}
                                        </span>
                                        <span className="shrink-0 text-right">
                                            <span className="text-lg font-medium md:text-base">
                                                {formatNumber(item.weightKg)}
                                            </span>{' '}
                                            <span className="text-base text-muted-foreground md:text-sm">
                                                กก.
                                            </span>
                                            {item.fruitCount > 0 && (
                                                <span className="block text-base text-muted-foreground md:text-sm">
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
                    <h2 className="text-xl font-semibold md:text-lg">
                        ประวัติ
                    </h2>
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
                                        <div className="flex min-w-0 items-center gap-2">
                                            <p className="truncate text-lg font-medium md:text-base">
                                                {entry.title}
                                            </p>
                                            {entry.fromAi && (
                                                <Badge variant="outline">
                                                    <span className="sr-only">
                                                        บันทึกโดย{' '}
                                                    </span>
                                                    AI
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-base text-muted-foreground md:text-sm">
                                            {formatThaiDate(entry.date)}
                                            {entry.detail &&
                                                ` · ${entry.detail}`}
                                        </p>
                                        {entry.kind === 'observation' &&
                                            entry.photos.length > 0 && (
                                                <ul className="mt-2 flex flex-wrap gap-2">
                                                    {entry.photos.map(
                                                        (url, index) => (
                                                            <li key={url}>
                                                                <a
                                                                    href={url}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    aria-label={`เปิดรูปที่ ${index + 1} ของ ${entry.title}`}
                                                                >
                                                                    <img
                                                                        src={
                                                                            url
                                                                        }
                                                                        alt=""
                                                                        loading="lazy"
                                                                        className="size-16 rounded-md border object-cover"
                                                                    />
                                                                </a>
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            )}
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
                                                    destroyUrl(entry),
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
                                    <CareRecordFormBody
                                        defaultType={careType}
                                        errors={errors}
                                        initialSelected={[tree.id]}
                                        openIssues={openIssues}
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

            <Dialog open={observing} onOpenChange={setObserving}>
                <DialogContent className="max-h-[90dvh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>บันทึกสภาพต้น</DialogTitle>
                        <DialogDescription>{tree.code}</DialogDescription>
                    </DialogHeader>
                    <ObservationForm
                        tree={tree}
                        findings={findings}
                        openIssues={openIssues}
                        onSuccess={() => setObserving(false)}
                    />
                </DialogContent>
            </Dialog>

            <Dialog
                open={followingUp !== null}
                onOpenChange={(open) => !open && setFollowingUp(null)}
            >
                <DialogContent className="max-h-[90dvh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>ดูซ้ำ</DialogTitle>
                        <DialogDescription>
                            {tree.code} · {followingUp?.finding}
                        </DialogDescription>
                    </DialogHeader>
                    {followingUp && (
                        <FollowUpForm
                            key={followingUp.id}
                            issue={followingUp}
                            onSuccess={() => setFollowingUp(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={editingEntry !== null}
                onOpenChange={(open) => !open && setEditingEntry(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editingEntry?.kind === 'harvest' && 'แก้ไขผลผลิต'}
                            {editingEntry?.kind === 'observation' &&
                                'แก้ไขบันทึกสภาพต้น'}
                            {editingEntry?.kind === 'care' &&
                                'แก้ไขบันทึกการดูแล'}
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
                    {editingEntry?.kind === 'observation' && (
                        <ObservationEditForm
                            key={`observation-${editingEntry.id}`}
                            observation={editingEntry.record}
                            hasSeverity={
                                findings.find(
                                    (finding) =>
                                        finding.id ===
                                        editingEntry.record.finding_id,
                                )?.kind === 'problem'
                            }
                            onSuccess={() => setEditingEntry(null)}
                        />
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
                                    <CareRecordFormBody
                                        record={editingEntry.record}
                                        errors={errors}
                                        trees={activeTrees}
                                        attachedTrees={
                                            editingEntry.record.trees
                                        }
                                        initialSelected={(
                                            editingEntry.record.trees ?? []
                                        ).map((item) => item.id)}
                                        openIssues={openIssues}
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
