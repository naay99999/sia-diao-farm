import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import CareRecordController from '@/actions/App/Http/Controllers/Farm/CareRecordController';
import HarvestController from '@/actions/App/Http/Controllers/Farm/HarvestController';
import ObservationController from '@/actions/App/Http/Controllers/Farm/ObservationController';
import SaleController from '@/actions/App/Http/Controllers/Farm/SaleController';
import { useConfirm } from '@/components/confirm-dialog';
import { AiBadge } from '@/components/farm/ai-badge';
import { CareRecordFormBody } from '@/components/farm/care-record-form';
import { EmptyState } from '@/components/farm/empty-state';
import { HarvestFields } from '@/components/farm/harvest-form';
import { ObservationEditForm } from '@/components/farm/observation-form';
import { RecordFilters } from '@/components/farm/record-filters';
import { RowActions } from '@/components/farm/row-actions';
import { SaleFields } from '@/components/farm/sale-form';
import { SubmitButton } from '@/components/farm/submit-button';
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
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatBaht } from '@/lib/format';
import { formatThaiDate } from '@/lib/thai-date';
import { show as showOrchard } from '@/routes/orchards';
import { create as createCareRecord } from '@/routes/orchards/care-records';
import { create as createHarvest } from '@/routes/orchards/harvests';
import { index as recordsIndex } from '@/routes/records';
import { severityLabels } from '@/types/farm';
import type {
    CareRow,
    HarvestRow,
    IssueSummary,
    ObservationRow,
    Paginated,
    RecordFilterOptions,
    RecordFilterValues,
    RecordType,
    SaleRow,
    Tree,
} from '@/types/farm';

type AnyRow = CareRow | ObservationRow | HarvestRow | SaleRow;

type FarmIssue = IssueSummary & { orchardId: number };

/**
 * One line of the list in the shape both the desktop table and the phone rows read.
 */
type RecordLine = {
    id: number;
    date: string;
    where: string;
    title: string;
    note: string | null;
    amount: string;
    status: string | null;
    createdBy: string | null;
    fromAi: boolean;
    aiBy: string | null;
    deleteDescription: string;
};

const typeMeta: Record<
    RecordType,
    {
        label: string;
        amountLabel: string;
        newLabel: string;
        emptyText: string;
        filteredEmptyText: string;
    }
> = {
    care: {
        label: 'การดูแล',
        amountLabel: 'ปริมาณ',
        newLabel: 'ใส่ปุ๋ยหรือฉีดยา',
        emptyText: 'ยังไม่มีบันทึกการดูแล',
        filteredEmptyText: 'ไม่มีบันทึกการดูแลตามตัวกรองนี้',
    },
    observations: {
        label: 'สภาพต้น/ปัญหา',
        amountLabel: 'ความรุนแรง',
        newLabel: 'บันทึกสภาพต้น',
        emptyText: 'ยังไม่มีบันทึกสภาพต้น',
        filteredEmptyText: 'ไม่มีบันทึกสภาพต้นตามตัวกรองนี้',
    },
    harvests: {
        label: 'ผลผลิต',
        amountLabel: 'น้ำหนัก',
        newLabel: 'บันทึกผลผลิต',
        emptyText: 'ยังไม่มีบันทึกผลผลิต',
        filteredEmptyText: 'ไม่มีบันทึกผลผลิตตามตัวกรองนี้',
    },
    sales: {
        label: 'การขาย',
        amountLabel: 'จำนวนเงิน',
        newLabel: 'บันทึกการขาย',
        emptyText: 'ยังไม่มีบันทึกการขาย',
        filteredEmptyText: 'ไม่มีบันทึกการขายตามตัวกรองนี้',
    },
};

const outcomeLabels = { resolved: 'หายแล้ว', not_resolved: 'ยังไม่หาย' };

function toLine(type: RecordType, row: AnyRow): RecordLine {
    const base = {
        id: row.id,
        createdBy: row.createdBy,
        fromAi: row.fromAi,
        aiBy: row.aiBy,
    };

    switch (type) {
        case 'care': {
            const care = row as CareRow;
            const where = (care.trees ?? []).length;

            return {
                ...base,
                date: care.performed_on,
                where: `${care.orchard.name} · ${where} ต้น`,
                title: [care.typeLabel, care.product].filter(Boolean).join(' '),
                note: care.notes,
                amount: care.quantity ?? '-',
                status: null,
                deleteDescription: `${care.typeLabel} ${care.orchard.name} วันที่ ${formatThaiDate(care.performed_on)}`,
            };
        }
        case 'observations': {
            const observation = row as ObservationRow;
            const status = observation.outcome
                ? outcomeLabels[observation.outcome]
                : observation.issueStatus === 'open'
                  ? 'ปัญหายังเปิดอยู่'
                  : observation.issueStatus === 'resolved'
                    ? 'ปัญหาหายแล้ว'
                    : null;

            return {
                ...base,
                date: observation.observed_on,
                where: `${observation.orchard.name} · ต้น ${observation.tree.code}`,
                title: observation.finding.name,
                note: observation.notes,
                amount: observation.severity
                    ? severityLabels[observation.severity]
                    : '-',
                status,
                deleteDescription: `${observation.finding.name} ต้น ${observation.tree.code} วันที่ ${formatThaiDate(observation.observed_on)}`,
            };
        }
        case 'harvests': {
            const harvest = row as HarvestRow;

            return {
                ...base,
                date: harvest.harvested_on,
                where: `${harvest.orchard.name} · ต้น ${harvest.tree.code}`,
                title: harvest.fruit_count
                    ? `เก็บผลผลิต ${harvest.fruit_count} ผล`
                    : 'เก็บผลผลิต',
                note: harvest.notes,
                amount: `${Number(harvest.weight_kg).toLocaleString('th-TH')} กก.`,
                status: null,
                deleteDescription: `ผลผลิต ${harvest.weight_kg} กก. ต้น ${harvest.tree.code} วันที่ ${formatThaiDate(harvest.harvested_on)}`,
            };
        }
        case 'sales': {
            const sale = row as SaleRow;

            return {
                ...base,
                date: sale.sold_on,
                where: sale.orchard.name,
                title: sale.buyer_name
                    ? `ขายให้ ${sale.buyer_name}`
                    : 'ขายผลผลิต',
                note: sale.notes,
                amount: `${formatBaht(sale.amount)} บาท`,
                status: `${Number(sale.weight_kg).toLocaleString('th-TH')} กก.`,
                deleteDescription: `ขาย ${formatBaht(sale.amount)} บาท วันที่ ${formatThaiDate(sale.sold_on)}`,
            };
        }
    }
}

function destroyUrl(type: RecordType, id: number): string {
    switch (type) {
        case 'care':
            return CareRecordController.destroy.url(id);
        case 'observations':
            return ObservationController.destroy.url(id);
        case 'harvests':
            return HarvestController.destroy.url(id);
        case 'sales':
            return SaleController.destroy.url(id);
    }
}

function NewRecordMenu({
    type,
    orchards,
}: {
    type: RecordType;
    orchards: RecordFilterOptions['orchards'];
}) {
    const hrefFor = (orchardId: number) => {
        switch (type) {
            case 'care':
                return createCareRecord(orchardId);
            case 'harvests':
                return createHarvest(orchardId);
            default:
                return showOrchard(orchardId);
        }
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button disabled={orchards.length === 0}>
                    จดใหม่
                    <ChevronDown aria-hidden />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-48">
                {orchards.map((orchard) => (
                    <DropdownMenuItem key={orchard.id} asChild>
                        <Link href={hrefFor(orchard.id)}>{orchard.name}</Link>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function Pagination({ records }: { records: Paginated<unknown> }) {
    if (records.total === 0) {
        return null;
    }

    const previous = records.links[0]?.url;
    const next = records.links[records.links.length - 1]?.url;

    return (
        <nav
            aria-label="เลือกหน้า"
            className="flex flex-wrap items-center justify-between gap-3"
        >
            <p className="text-base text-muted-foreground md:text-sm">
                แสดง {records.from?.toLocaleString('th-TH')}–
                {records.to?.toLocaleString('th-TH')} จาก{' '}
                {records.total.toLocaleString('th-TH')} รายการ
            </p>
            {records.last_page > 1 && (
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        disabled={!previous}
                        asChild={Boolean(previous)}
                    >
                        {previous ? (
                            <Link href={previous} preserveScroll>
                                ก่อนหน้า
                            </Link>
                        ) : (
                            'ก่อนหน้า'
                        )}
                    </Button>
                    <span className="text-base md:text-sm">
                        หน้า {records.current_page} จาก {records.last_page}
                    </span>
                    <Button
                        variant="outline"
                        disabled={!next}
                        asChild={Boolean(next)}
                    >
                        {next ? (
                            <Link href={next} preserveScroll>
                                ถัดไป
                            </Link>
                        ) : (
                            'ถัดไป'
                        )}
                    </Button>
                </div>
            )}
        </nav>
    );
}

export default function RecordsIndex({
    type,
    records,
    filters,
    orchards,
    seasons,
    members,
    orchardTrees = {},
    openIssues = [],
}: {
    type: RecordType;
    records: Paginated<AnyRow>;
    filters: RecordFilterValues;
} & RecordFilterOptions & {
        orchardTrees?: Record<number, Pick<Tree, 'id' | 'code'>[]>;
        openIssues?: FarmIssue[];
    }) {
    const confirm = useConfirm();
    const [editing, setEditing] = useState<AnyRow | null>(null);
    const meta = typeMeta[type];
    const isFiltered = Object.values(filters).some((value) => value !== null);
    const lines = records.data.map((row) => ({ row, line: toLine(type, row) }));

    setLayoutProps({
        breadcrumbs: [
            { title: 'บันทึก', href: recordsIndex.url('care') },
            { title: meta.label, href: recordsIndex.url(type) },
        ],
    });

    const remove = async (line: RecordLine) => {
        if (
            await confirm({
                title: 'ลบบันทึกนี้?',
                description: line.deleteDescription,
                confirmLabel: 'ลบ',
                destructive: true,
            })
        ) {
            router.delete(destroyUrl(type, line.id), { preserveScroll: true });
        }
    };

    const closeEditing = () => setEditing(null);

    return (
        <>
            <Head title={`บันทึก${meta.label}`} />

            <div className="flex flex-wrap items-end justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <RecordFilters
                        filters={filters}
                        orchards={orchards}
                        seasons={seasons}
                        members={members}
                    />
                </div>
                <NewRecordMenu type={type} orchards={orchards} />
            </div>

            {lines.length === 0 ? (
                <EmptyState>
                    {isFiltered ? meta.filteredEmptyText : meta.emptyText}
                </EmptyState>
            ) : (
                <>
                    <div className="hidden rounded-lg border bg-card md:block">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>วันที่</TableHead>
                                    <TableHead>สวน / ต้น</TableHead>
                                    <TableHead>รายการ</TableHead>
                                    <TableHead className="text-right">
                                        {meta.amountLabel}
                                    </TableHead>
                                    <TableHead>คนที่จด</TableHead>
                                    <TableHead className="w-12">
                                        <span className="sr-only">
                                            ตัวเลือก
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {lines.map(({ row, line }) => (
                                    <TableRow key={line.id}>
                                        <TableCell>
                                            {formatThaiDate(line.date)}
                                        </TableCell>
                                        <TableCell>{line.where}</TableCell>
                                        <TableCell className="max-w-80 whitespace-normal">
                                            <span className="font-medium">
                                                {line.title}
                                            </span>
                                            {line.status && (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-2"
                                                >
                                                    {line.status}
                                                </Badge>
                                            )}
                                            {line.note && (
                                                <span className="block text-base text-muted-foreground md:text-sm">
                                                    {line.note}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {line.amount}
                                        </TableCell>
                                        <TableCell>
                                            <span className="flex items-center gap-2">
                                                {line.createdBy ?? '-'}
                                                {line.fromAi && (
                                                    <AiBadge by={line.aiBy} />
                                                )}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <RowActions
                                                label={`${line.title} ${formatThaiDate(line.date)}`}
                                                onEdit={() => setEditing(row)}
                                                onDelete={() => remove(line)}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    <ul className="divide-y rounded-lg border bg-card md:hidden">
                        {lines.map(({ row, line }) => (
                            <li
                                key={line.id}
                                className="flex items-center gap-3 py-3 pr-3 pl-4"
                            >
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-baseline justify-between gap-3">
                                        <p className="truncate text-lg font-medium">
                                            {line.title}
                                        </p>
                                        <p className="shrink-0 text-lg font-medium">
                                            {line.amount}
                                        </p>
                                    </div>
                                    <p className="truncate text-base text-muted-foreground">
                                        {formatThaiDate(line.date)} ·{' '}
                                        {line.where}
                                    </p>
                                    <p className="flex flex-wrap items-center gap-x-2 text-base text-muted-foreground">
                                        {line.createdBy ?? '-'}
                                        {line.fromAi && (
                                            <AiBadge by={line.aiBy} />
                                        )}
                                        {line.status && (
                                            <Badge variant="secondary">
                                                {line.status}
                                            </Badge>
                                        )}
                                    </p>
                                    {line.note && (
                                        <p className="text-base text-muted-foreground">
                                            {line.note}
                                        </p>
                                    )}
                                </div>
                                <RowActions
                                    label={`${line.title} ${formatThaiDate(line.date)}`}
                                    onEdit={() => setEditing(row)}
                                    onDelete={() => remove(line)}
                                />
                            </li>
                        ))}
                    </ul>
                </>
            )}

            <Pagination records={records} />

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && closeEditing()}
            >
                <DialogContent className="max-h-[90dvh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>แก้ไขบันทึก{meta.label}</DialogTitle>
                        <DialogDescription>
                            {editing && toLine(type, editing).where}
                        </DialogDescription>
                    </DialogHeader>
                    {editing && type === 'care' && (
                        <CareEditForm
                            key={editing.id}
                            record={editing as CareRow}
                            trees={
                                orchardTrees[(editing as CareRow).orchard.id] ??
                                []
                            }
                            openIssues={openIssues.filter(
                                (issue) =>
                                    issue.orchardId ===
                                    (editing as CareRow).orchard.id,
                            )}
                            onSuccess={closeEditing}
                        />
                    )}
                    {editing && type === 'harvests' && (
                        <Form
                            key={editing.id}
                            {...HarvestController.update.form(editing.id)}
                            onSuccess={closeEditing}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <HarvestFields
                                        harvest={editing as HarvestRow}
                                        errors={errors}
                                    />
                                    <SubmitButton processing={processing} />
                                </>
                            )}
                        </Form>
                    )}
                    {editing && type === 'sales' && (
                        <Form
                            key={editing.id}
                            {...SaleController.update.form(editing.id)}
                            onSuccess={closeEditing}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <SaleFields
                                        sale={editing as SaleRow}
                                        errors={errors}
                                    />
                                    <SubmitButton processing={processing} />
                                </>
                            )}
                        </Form>
                    )}
                    {editing && type === 'observations' && (
                        <ObservationEditForm
                            key={editing.id}
                            observation={editing as ObservationRow}
                            hasSeverity={
                                (editing as ObservationRow).finding.kind ===
                                'problem'
                            }
                            onSuccess={closeEditing}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function CareEditForm({
    record,
    trees,
    openIssues,
    onSuccess,
}: {
    record: CareRow;
    trees: Pick<Tree, 'id' | 'code'>[];
    openIssues: IssueSummary[];
    onSuccess: () => void;
}) {
    return (
        <Form
            {...CareRecordController.update.form(record.id)}
            onSuccess={onSuccess}
            className="space-y-4"
        >
            {({ processing, errors }) => (
                <>
                    <CareRecordFormBody
                        record={record}
                        errors={errors}
                        trees={trees}
                        attachedTrees={record.trees}
                        initialSelected={(record.trees ?? []).map(
                            (tree) => tree.id,
                        )}
                        openIssues={openIssues}
                    />
                    <SubmitButton processing={processing} />
                </>
            )}
        </Form>
    );
}
