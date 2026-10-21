import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ChevronRight, MoreVertical, Search } from 'lucide-react';
import { useState } from 'react';
import CareRecordController from '@/actions/App/Http/Controllers/Farm/CareRecordController';
import OrchardController from '@/actions/App/Http/Controllers/Farm/OrchardController';
import SaleController from '@/actions/App/Http/Controllers/Farm/SaleController';
import SeasonController from '@/actions/App/Http/Controllers/Farm/SeasonController';
import TreeController from '@/actions/App/Http/Controllers/Farm/TreeController';
import { useConfirm } from '@/components/confirm-dialog';
import {
    CareRecordFields,
    TreePicker,
} from '@/components/farm/care-record-form';
import { EmptyState } from '@/components/farm/empty-state';
import { RowActions } from '@/components/farm/row-actions';
import { formatPricePerKg, SaleFields } from '@/components/farm/sale-form';
import { SectionTabs } from '@/components/farm/section-tabs';
import { SubmitButton } from '@/components/farm/submit-button';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import OrchardFormFields from '@/components/orchard-form-fields';
import SeasonFormFields from '@/components/season-form-fields';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatBaht, formatNumber } from '@/lib/format';
import { formatThaiDate } from '@/lib/thai-date';
import { index, qr, show } from '@/routes/orchards';
import { create as createCareRecord } from '@/routes/orchards/care-records';
import { create as createHarvest } from '@/routes/orchards/harvests';
import { show as showSeason } from '@/routes/seasons';
import { show as showTree } from '@/routes/trees';
import { careTypeLabels } from '@/types/farm';
import type { CareRecord, Orchard, Sale, Season, Tree } from '@/types/farm';

type OrchardTab = 'trees' | 'care' | 'sales' | 'seasons';

const orchardTabs: OrchardTab[] = ['trees', 'care', 'sales', 'seasons'];

function rememberedTab(orchardId: number): OrchardTab {
    try {
        const stored = window.sessionStorage.getItem(
            `orchard-tab-${orchardId}`,
        );

        return orchardTabs.includes(stored as OrchardTab)
            ? (stored as OrchardTab)
            : 'trees';
    } catch {
        return 'trees';
    }
}

function rememberTab(orchardId: number, tab: OrchardTab): void {
    try {
        window.sessionStorage.setItem(`orchard-tab-${orchardId}`, tab);
    } catch {
        // Private mode or blocked storage: the tab simply resets on the next visit.
    }
}

export default function OrchardShow({
    orchard,
    trees,
    showRemoved,
    currentSeason,
    seasons,
    recentCareRecords,
    activeTrees,
    sales,
    currentSeasonYieldKg,
}: {
    orchard: Orchard;
    trees: Tree[];
    showRemoved: boolean;
    currentSeason: Season | null;
    seasons: Season[];
    recentCareRecords: CareRecord[];
    activeTrees: Pick<Tree, 'id' | 'code'>[];
    sales: Sale[];
    currentSeasonYieldKg: Record<number, number>;
}) {
    const confirm = useConfirm();
    const [tab, setTab] = useState<OrchardTab>(() => rememberedTab(orchard.id));
    const [saleDialog, setSaleDialog] = useState<Sale | 'new' | null>(null);
    const [editingCare, setEditingCare] = useState<CareRecord | null>(null);
    const [seasonDialog, setSeasonDialog] = useState<'start' | 'edit' | null>(
        null,
    );
    const [editing, setEditing] = useState(false);
    const [addingOne, setAddingOne] = useState(false);
    const [addingMany, setAddingMany] = useState(false);
    const [treeQuery, setTreeQuery] = useState('');

    setLayoutProps({
        breadcrumbs: [
            { title: 'สวน', href: index() },
            { title: orchard.name, href: show(orchard.id) },
        ],
    });

    const selectTab = (next: OrchardTab) => {
        setTab(next);
        rememberTab(orchard.id, next);
    };

    const deleteOrchard = async () => {
        if (
            await confirm({
                title: `ลบ${orchard.name}?`,
                description:
                    'ต้นไม้ รอบการผลิต บันทึกการดูแล ผลผลิต และการขายทั้งหมดในสวนนี้จะถูกลบไปด้วย',
                confirmLabel: 'ลบสวน',
                destructive: true,
            })
        ) {
            router.delete(OrchardController.destroy.url(orchard.id));
        }
    };

    const normalizedQuery = treeQuery.trim().toLowerCase();
    const visibleTrees = normalizedQuery
        ? trees.filter((tree) =>
              `${tree.code} ${tree.variety ?? ''}`
                  .toLowerCase()
                  .includes(normalizedQuery),
          )
        : trees;
    const maxTreeYield = Math.max(0, ...Object.values(currentSeasonYieldKg));

    return (
        <div className="flex flex-1 flex-col gap-6 px-4 py-5 md:px-6 md:py-6">
            <Head title={orchard.name} />
            <div className="flex items-start justify-between gap-3">
                <Heading
                    title={orchard.name}
                    description={orchard.location ?? undefined}
                />
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="outline"
                            size="icon"
                            className="shrink-0"
                            aria-label="ตัวเลือกของสวน"
                        >
                            <MoreVertical />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="min-w-48">
                        <DropdownMenuItem onSelect={() => setEditing(true)}>
                            แก้ไขข้อมูลสวน
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link href={qr(orchard.id)}>พิมพ์ป้าย QR</Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={deleteOrchard}
                        >
                            ลบสวน
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            {orchard.notes && (
                <p className="-mt-3 text-base text-muted-foreground md:text-sm">
                    {orchard.notes}
                </p>
            )}

            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <SeasonPanel
                    orchard={orchard}
                    currentSeason={currentSeason}
                    currentSeasonYieldKg={currentSeasonYieldKg}
                    activeTreeCount={activeTrees.length}
                    onStart={() => setSeasonDialog('start')}
                    onEdit={() => setSeasonDialog('edit')}
                />

                <div className="min-w-0 space-y-4 lg:col-start-1 lg:row-start-1">
                    <SectionTabs
                        label="ข้อมูลในสวน"
                        value={tab}
                        onChange={selectTab}
                        tabs={[
                            {
                                value: 'trees',
                                label: 'ต้นไม้',
                                count: trees.length,
                            },
                            { value: 'care', label: 'การดูแล' },
                            { value: 'sales', label: 'การขาย' },
                            {
                                value: 'seasons',
                                label: 'รอบที่ผ่านมา',
                                count: seasons.length,
                            },
                        ]}
                    />

                    {tab === 'trees' && (
                        <section
                            id="panel-trees"
                            role="tabpanel"
                            aria-labelledby="tab-trees"
                            className="space-y-3"
                        >
                            <div className="flex gap-2">
                                <div className="relative flex-1">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        type="search"
                                        value={treeQuery}
                                        onChange={(event) =>
                                            setTreeQuery(event.target.value)
                                        }
                                        placeholder="ค้นหารหัสต้นหรือพันธุ์"
                                        aria-label="ค้นหาต้นไม้"
                                        className="pl-9"
                                    />
                                </div>
                                <Button
                                    variant="outline"
                                    onClick={() => setAddingOne(true)}
                                >
                                    เพิ่มต้นไม้
                                </Button>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            className="shrink-0"
                                            aria-label="ตัวเลือกรายการต้นไม้"
                                        >
                                            <MoreVertical />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        className="min-w-52"
                                    >
                                        <DropdownMenuItem
                                            onSelect={() => setAddingMany(true)}
                                        >
                                            เพิ่มหลายต้นพร้อมกัน
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onSelect={() =>
                                                router.get(
                                                    show.url(orchard.id),
                                                    showRemoved
                                                        ? {}
                                                        : { show_removed: 1 },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {showRemoved
                                                ? 'ซ่อนต้นที่ไม่อยู่แล้ว'
                                                : 'แสดงต้นที่ไม่อยู่แล้ว'}
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>

                            {trees.length === 0 ? (
                                <EmptyState
                                    action={
                                        <Button
                                            variant="outline"
                                            onClick={() => setAddingMany(true)}
                                        >
                                            เพิ่มหลายต้นพร้อมกัน
                                        </Button>
                                    }
                                >
                                    ยังไม่มีต้นไม้ในสวนนี้
                                </EmptyState>
                            ) : visibleTrees.length === 0 ? (
                                <EmptyState>
                                    ไม่พบต้นที่ตรงกับ “{treeQuery.trim()}”
                                </EmptyState>
                            ) : (
                                <div className="overflow-hidden rounded-lg border bg-card">
                                    <ul className="-mb-px grid sm:grid-cols-2">
                                        {visibleTrees.map((tree) => {
                                            const yieldKg =
                                                currentSeasonYieldKg[tree.id] ??
                                                0;

                                            return (
                                                <li
                                                    key={tree.id}
                                                    className="border-b sm:odd:border-r"
                                                >
                                                    <Link
                                                        href={showTree(tree.id)}
                                                        className="block px-4 py-3 transition-colors hover:bg-accent/60 active:bg-accent"
                                                    >
                                                        <div className="flex items-baseline justify-between gap-3">
                                                            <span className="truncate text-lg font-medium md:text-base">
                                                                {tree.code}
                                                            </span>
                                                            {tree.removed_on ? (
                                                                <Badge variant="secondary">
                                                                    ไม่อยู่แล้ว
                                                                </Badge>
                                                            ) : (
                                                                currentSeason && (
                                                                    <span
                                                                        className={
                                                                            yieldKg >
                                                                            0
                                                                                ? 'text-lg font-medium md:text-base'
                                                                                : 'text-lg text-muted-foreground md:text-base'
                                                                        }
                                                                    >
                                                                        {formatNumber(
                                                                            yieldKg,
                                                                        )}{' '}
                                                                        <span className="text-base font-normal text-muted-foreground md:text-sm">
                                                                            กก.
                                                                        </span>
                                                                    </span>
                                                                )
                                                            )}
                                                        </div>
                                                        <div className="mt-0.5 flex items-center justify-between gap-3 text-base text-muted-foreground md:text-sm">
                                                            <span className="truncate">
                                                                {tree.variety ??
                                                                    'ไม่ระบุพันธุ์'}
                                                            </span>
                                                        </div>
                                                        {currentSeason &&
                                                            yieldKg > 0 && (
                                                                <div
                                                                    aria-hidden
                                                                    className="mt-2 h-1 rounded-full bg-primary"
                                                                    style={{
                                                                        width: `${(yieldKg / maxTreeYield) * 100}%`,
                                                                    }}
                                                                />
                                                            )}
                                                    </Link>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                </div>
                            )}
                        </section>
                    )}

                    {tab === 'care' && (
                        <section
                            id="panel-care"
                            role="tabpanel"
                            aria-labelledby="tab-care"
                            className="space-y-3"
                        >
                            <div className="flex items-center justify-between gap-3">
                                <h2 className="text-xl font-semibold md:text-lg">
                                    การดูแลล่าสุด
                                </h2>
                                <Button variant="outline" asChild>
                                    <Link href={createCareRecord(orchard.id)}>
                                        บันทึกการดูแล
                                    </Link>
                                </Button>
                            </div>
                            {recentCareRecords.length === 0 ? (
                                <EmptyState>ยังไม่มีบันทึกการดูแล</EmptyState>
                            ) : (
                                <ul className="divide-y rounded-lg border bg-card">
                                    {recentCareRecords.map((record) => (
                                        <li
                                            key={record.id}
                                            className="flex items-center gap-3 py-3 pr-3 pl-4"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-lg font-medium md:text-base">
                                                    {
                                                        careTypeLabels[
                                                            record.type
                                                        ]
                                                    }
                                                    {record.product &&
                                                        ` · ${record.product}`}
                                                </p>
                                                <p className="text-base text-muted-foreground md:text-sm">
                                                    {formatThaiDate(
                                                        record.performed_on,
                                                    )}{' '}
                                                    ·{' '}
                                                    {record.trees?.length ?? 0}{' '}
                                                    ต้น
                                                    {record.quantity &&
                                                        ` · ${record.quantity}`}
                                                </p>
                                            </div>
                                            <RowActions
                                                label={`${careTypeLabels[record.type]} ${formatThaiDate(record.performed_on)}`}
                                                onEdit={() =>
                                                    setEditingCare(record)
                                                }
                                                onDelete={async () => {
                                                    if (
                                                        await confirm({
                                                            title: 'ลบบันทึกการดูแลนี้?',
                                                            description: `${careTypeLabels[record.type]} วันที่ ${formatThaiDate(record.performed_on)} กับ ${record.trees?.length ?? 0} ต้น`,
                                                            confirmLabel: 'ลบ',
                                                            destructive: true,
                                                        })
                                                    ) {
                                                        router.delete(
                                                            CareRecordController.destroy.url(
                                                                record.id,
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
                    )}

                    {tab === 'sales' && (
                        <SalesPanel
                            sales={sales}
                            onCreate={() => setSaleDialog('new')}
                            onEdit={setSaleDialog}
                        />
                    )}

                    {tab === 'seasons' && (
                        <section
                            id="panel-seasons"
                            role="tabpanel"
                            aria-labelledby="tab-seasons"
                            className="space-y-3"
                        >
                            <h2 className="text-xl font-semibold md:text-lg">
                                รอบที่ปิดแล้ว
                            </h2>
                            {seasons.length === 0 ? (
                                <EmptyState>ยังไม่มีรอบที่ปิดแล้ว</EmptyState>
                            ) : (
                                <ul className="divide-y rounded-lg border bg-card">
                                    {seasons.map((season) => (
                                        <li key={season.id}>
                                            <Link
                                                href={showSeason(season.id)}
                                                className="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-accent/60 active:bg-accent"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-lg font-medium md:text-base">
                                                        {season.name}
                                                    </p>
                                                    <p className="text-base text-muted-foreground md:text-sm">
                                                        {formatThaiDate(
                                                            season.started_on,
                                                        )}{' '}
                                                        –{' '}
                                                        {season.ended_on
                                                            ? formatThaiDate(
                                                                  season.ended_on,
                                                              )
                                                            : 'ปัจจุบัน'}
                                                    </p>
                                                </div>
                                                <ChevronRight className="size-5 text-muted-foreground" />
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    )}
                </div>
            </div>

            <Dialog open={editing} onOpenChange={setEditing}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>แก้ไขข้อมูลสวน</DialogTitle>
                        <DialogDescription>{orchard.name}</DialogDescription>
                    </DialogHeader>
                    <Form
                        {...OrchardController.update.form(orchard.id)}
                        onSuccess={() => setEditing(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <OrchardFormFields
                                    orchard={orchard}
                                    errors={errors}
                                />
                                <SubmitButton processing={processing} />
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={seasonDialog !== null}
                onOpenChange={(open) => !open && setSeasonDialog(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {seasonDialog === 'edit'
                                ? 'แก้ไขรอบ'
                                : 'เริ่มรอบใหม่'}
                        </DialogTitle>
                        <DialogDescription>
                            กำหนดชื่อและช่วงวันของรอบการผลิต
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        {...(seasonDialog === 'edit' && currentSeason
                            ? SeasonController.update.form(currentSeason.id)
                            : SeasonController.store.form(orchard.id))}
                        onSuccess={() => setSeasonDialog(null)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <SeasonFormFields
                                    season={
                                        seasonDialog === 'edit'
                                            ? (currentSeason ?? undefined)
                                            : undefined
                                    }
                                    errors={errors}
                                />
                                <SubmitButton processing={processing} />
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog open={addingOne} onOpenChange={setAddingOne}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>เพิ่มต้นไม้</DialogTitle>
                        <DialogDescription>
                            เพิ่มต้นไม้ทีละต้น
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        {...TreeController.store.form(orchard.id)}
                        onSuccess={() => setAddingOne(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <TreeFormFields errors={errors} />
                                <SubmitButton processing={processing} />
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog open={addingMany} onOpenChange={setAddingMany}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>เพิ่มหลายต้นพร้อมกัน</DialogTitle>
                        <DialogDescription>
                            สร้างรหัสต้นไม้ต่อเนื่อง เช่น A-001, A-002
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        {...TreeController.storeBatch.form(orchard.id)}
                        onSuccess={() => setAddingMany(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="batch-prefix">
                                        คำนำหน้ารหัส
                                    </Label>
                                    <Input
                                        id="batch-prefix"
                                        name="prefix"
                                        required
                                        placeholder="เช่น A-"
                                    />
                                    <InputError message={errors.prefix} />
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="batch-start">
                                            เริ่มที่เลข
                                        </Label>
                                        <Input
                                            id="batch-start"
                                            name="start"
                                            type="number"
                                            inputMode="numeric"
                                            min={1}
                                            defaultValue={1}
                                            required
                                        />
                                        <InputError message={errors.start} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="batch-count">
                                            จำนวน (ต้น)
                                        </Label>
                                        <Input
                                            id="batch-count"
                                            name="count"
                                            type="number"
                                            inputMode="numeric"
                                            min={1}
                                            max={500}
                                            required
                                        />
                                        <InputError message={errors.count} />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="batch-variety">
                                        พันธุ์
                                    </Label>
                                    <Input
                                        id="batch-variety"
                                        name="variety"
                                        placeholder="เช่น หมอนทอง"
                                    />
                                    <InputError message={errors.variety} />
                                </div>
                                <SubmitButton processing={processing}>
                                    สร้างต้นไม้
                                </SubmitButton>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editingCare !== null}
                onOpenChange={(open) => !open && setEditingCare(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>แก้ไขบันทึกการดูแล</DialogTitle>
                        <DialogDescription>
                            แก้ไขรายละเอียดและต้นไม้ที่เกี่ยวข้อง
                        </DialogDescription>
                    </DialogHeader>
                    {editingCare && (
                        <Form
                            key={editingCare.id}
                            {...CareRecordController.update.form(
                                editingCare.id,
                            )}
                            onSuccess={() => setEditingCare(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <CareRecordFields
                                        record={editingCare}
                                        errors={errors}
                                    />
                                    <TreePicker
                                        trees={activeTrees}
                                        attachedTrees={editingCare.trees}
                                        initialSelected={(
                                            editingCare.trees ?? []
                                        ).map((tree) => tree.id)}
                                        errors={errors}
                                    />
                                    <SubmitButton processing={processing} />
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={saleDialog !== null}
                onOpenChange={(open) => !open && setSaleDialog(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {saleDialog === 'new'
                                ? 'บันทึกการขาย'
                                : 'แก้ไขการขาย'}
                        </DialogTitle>
                        <DialogDescription>
                            ระบุน้ำหนักและจำนวนเงินที่ขายได้
                        </DialogDescription>
                    </DialogHeader>
                    {saleDialog && (
                        <Form
                            key={saleDialog === 'new' ? 'new' : saleDialog.id}
                            {...(saleDialog === 'new'
                                ? SaleController.store.form(orchard.id)
                                : SaleController.update.form(saleDialog.id))}
                            onSuccess={() => setSaleDialog(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <SaleFields
                                        sale={
                                            saleDialog === 'new'
                                                ? undefined
                                                : saleDialog
                                        }
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

/**
 * The orchard's one question, "how is this season's harvest going?", with the two jobs done most in the field.
 */
function SeasonPanel({
    orchard,
    currentSeason,
    currentSeasonYieldKg,
    activeTreeCount,
    onStart,
    onEdit,
}: {
    orchard: Orchard;
    currentSeason: Season | null;
    currentSeasonYieldKg: Record<number, number>;
    activeTreeCount: number;
    onStart: () => void;
    onEdit: () => void;
}) {
    const confirm = useConfirm();
    const harvestedTreeCount = Object.values(currentSeasonYieldKg).filter(
        (weight) => weight > 0,
    ).length;
    const seasonYieldKg = Object.values(currentSeasonYieldKg).reduce(
        (sum, weight) => sum + weight,
        0,
    );

    const actions = (
        <div className="grid grid-cols-2 gap-2 lg:grid-cols-1">
            <Button className="h-12 text-base md:h-10 md:text-sm" asChild>
                <Link href={createHarvest(orchard.id)}>บันทึกผลผลิต</Link>
            </Button>
            <Button variant="outline" asChild>
                <Link href={createCareRecord(orchard.id)}>บันทึกการดูแล</Link>
            </Button>
        </div>
    );

    if (currentSeason === null) {
        return (
            <section className="space-y-4 rounded-lg border bg-card p-4 md:p-5 lg:sticky lg:top-6 lg:col-start-2 lg:row-start-1">
                <div>
                    <h2 className="text-xl font-semibold md:text-lg">
                        ยังไม่มีรอบที่เปิดอยู่
                    </h2>
                    <p className="text-base text-muted-foreground md:text-sm">
                        เริ่มรอบใหม่เพื่อนับผลผลิตและค่าใช้จ่ายของฤดูนี้
                    </p>
                </div>
                <div className="grid grid-cols-2 gap-2 lg:grid-cols-1">
                    <Button onClick={onStart}>เริ่มรอบใหม่</Button>
                    <Button variant="outline" asChild>
                        <Link href={createCareRecord(orchard.id)}>
                            บันทึกการดูแล
                        </Link>
                    </Button>
                </div>
            </section>
        );
    }

    return (
        <section className="space-y-4 rounded-lg border bg-card p-4 md:p-5 lg:sticky lg:top-6 lg:col-start-2 lg:row-start-1">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <h2 className="truncate text-xl font-semibold md:text-lg">
                        {currentSeason.name}
                    </h2>
                    <p className="text-base text-muted-foreground md:text-sm">
                        เริ่ม {formatThaiDate(currentSeason.started_on)} ·
                        ยังไม่ปิดรอบ
                    </p>
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="-mt-1 -mr-2 shrink-0 text-muted-foreground"
                            aria-label="ตัวเลือกของรอบ"
                        >
                            <MoreVertical />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="min-w-44">
                        <DropdownMenuItem asChild>
                            <Link href={showSeason(currentSeason.id)}>
                                ดูสรุปรอบ
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={onEdit}>
                            แก้ไขรอบ
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={async () => {
                                if (
                                    await confirm({
                                        title: `ปิด${currentSeason.name}วันนี้?`,
                                        description:
                                            'ผลผลิตที่บันทึกหลังจากวันนี้จะไม่นับรวมในรอบนี้',
                                        confirmLabel: 'ปิดรอบ',
                                    })
                                ) {
                                    router.patch(
                                        SeasonController.close.url(
                                            currentSeason.id,
                                        ),
                                    );
                                }
                            }}
                        >
                            ปิดรอบ
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={async () => {
                                if (
                                    await confirm({
                                        title: `ลบ${currentSeason.name}?`,
                                        description:
                                            'ค่าใช้จ่ายที่ผูกกับรอบนี้จะไม่ผูกกับรอบใดอีก',
                                        confirmLabel: 'ลบรอบ',
                                        destructive: true,
                                    })
                                ) {
                                    router.delete(
                                        SeasonController.destroy.url(
                                            currentSeason.id,
                                        ),
                                    );
                                }
                            }}
                        >
                            ลบรอบ
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <Link
                href={showSeason(currentSeason.id)}
                className="block rounded-md bg-muted px-4 py-3 transition-colors hover:bg-accent"
            >
                <p className="text-base text-muted-foreground md:text-sm">
                    ผลผลิตรอบนี้
                </p>
                <p className="text-3xl leading-[1.3] font-semibold text-foreground">
                    {formatNumber(seasonYieldKg)}{' '}
                    <span className="text-lg font-normal text-muted-foreground md:text-base">
                        กก.
                    </span>
                </p>
                <p className="text-base text-muted-foreground md:text-sm">
                    เก็บแล้ว {harvestedTreeCount} จาก {activeTreeCount} ต้น
                </p>
            </Link>

            {actions}
        </section>
    );
}

function SalesPanel({
    sales,
    onCreate,
    onEdit,
}: {
    sales: Sale[];
    onCreate: () => void;
    onEdit: (sale: Sale) => void;
}) {
    const confirm = useConfirm();
    const totalAmount = sales.reduce(
        (sum, sale) => sum + Number(sale.amount),
        0,
    );
    const totalWeight = sales.reduce(
        (sum, sale) => sum + Number(sale.weight_kg),
        0,
    );

    return (
        <section
            id="panel-sales"
            role="tabpanel"
            aria-labelledby="tab-sales"
            className="space-y-3"
        >
            <div className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                    <h2 className="text-xl font-semibold md:text-lg">
                        การขายทั้งหมด
                    </h2>
                    {sales.length > 0 && (
                        <p className="text-base text-muted-foreground md:text-sm">
                            {sales.length} ครั้ง · {formatBaht(totalAmount)} บาท
                            · {formatNumber(totalWeight)} กก.
                        </p>
                    )}
                </div>
                <Button variant="outline" onClick={onCreate}>
                    บันทึกการขาย
                </Button>
            </div>
            {sales.length === 0 ? (
                <EmptyState>ยังไม่มีบันทึกการขาย</EmptyState>
            ) : (
                <ul className="divide-y rounded-lg border bg-card">
                    {sales.map((sale) => (
                        <li
                            key={sale.id}
                            className="flex items-center gap-3 py-3 pr-3 pl-4"
                        >
                            <div className="min-w-0 flex-1">
                                <div className="flex items-baseline justify-between gap-3">
                                    <p className="truncate text-lg font-medium md:text-base">
                                        {formatThaiDate(sale.sold_on)}
                                        {sale.buyer_name && (
                                            <span className="font-normal text-muted-foreground">
                                                {' '}
                                                · {sale.buyer_name}
                                            </span>
                                        )}
                                    </p>
                                    <p className="shrink-0 text-lg font-medium md:text-base">
                                        {formatBaht(sale.amount)}{' '}
                                        <span className="text-base font-normal text-muted-foreground md:text-sm">
                                            บาท
                                        </span>
                                    </p>
                                </div>
                                <p className="text-base text-muted-foreground md:text-sm">
                                    {formatNumber(sale.weight_kg)} กก. ·{' '}
                                    {formatPricePerKg(sale)} บาท/กก.
                                </p>
                            </div>
                            <RowActions
                                label={`การขาย ${formatThaiDate(sale.sold_on)}`}
                                onEdit={() => onEdit(sale)}
                                onDelete={async () => {
                                    if (
                                        await confirm({
                                            title: 'ลบการขายนี้?',
                                            description: `${formatThaiDate(sale.sold_on)} · ${formatNumber(sale.weight_kg)} กก. · ${formatBaht(sale.amount)} บาท`,
                                            confirmLabel: 'ลบ',
                                            destructive: true,
                                        })
                                    ) {
                                        router.delete(
                                            SaleController.destroy.url(sale.id),
                                        );
                                    }
                                }}
                            />
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
