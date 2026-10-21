import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { EmptyState } from '@/components/farm/empty-state';
import Heading from '@/components/heading';
import { formatBaht, formatNumber } from '@/lib/format';
import { formatThaiDate } from '@/lib/thai-date';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/orchards';
import { show as showSeason } from '@/routes/seasons';
import { show as showTree } from '@/routes/trees';
import type { Orchard, Sale, Season, TreeSeasonYield } from '@/types/farm';

/** Rounds to satang so totals are computed from the figures exactly as displayed. */
const toSatang = (value: number): number => Math.round(value * 100) / 100;

function Figure({
    label,
    value,
    unit,
    detail,
}: {
    label: string;
    value: string;
    unit: string;
    detail?: string;
}) {
    return (
        <div className="bg-card px-4 py-3">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="text-xl font-medium md:text-2xl">
                {value}{' '}
                <span className="text-sm font-normal text-muted-foreground">
                    {unit}
                </span>
            </p>
            {detail && (
                <p className="text-sm text-muted-foreground">{detail}</p>
            )}
        </div>
    );
}

export default function SeasonShow({
    season,
    orchard,
    totalYieldKg,
    totalFruitCount,
    treeYields,
    salesTotal,
    salesWeightKg,
    sales,
    expensesTotal,
    careRecordCount,
}: {
    season: Season;
    orchard: Orchard;
    totalYieldKg: number;
    totalFruitCount: number;
    treeYields: TreeSeasonYield[];
    salesTotal: number;
    salesWeightKg: number;
    sales: Sale[];
    expensesTotal: number;
    careRecordCount: number;
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'สวน', href: index() },
            { title: orchard.name, href: show(orchard.id) },
            { title: season.name, href: showSeason(season.id) },
        ],
    });

    const shownSales = toSatang(salesTotal);
    const shownExpenses = toSatang(expensesTotal);
    const profit = toSatang(shownSales - shownExpenses);
    const maxTreeYield = Math.max(0, ...treeYields.map((row) => row.weightKg));
    const averagePrice =
        salesWeightKg > 0 ? formatBaht(salesTotal / salesWeightKg) : null;

    return (
        <div className="flex flex-1 flex-col gap-6 px-4 py-5 md:px-6 md:py-6">
            <Head title={season.name} />
            <Heading
                title={season.name}
                description={`${orchard.name} · ${formatThaiDate(season.started_on)} – ${
                    season.ended_on
                        ? formatThaiDate(season.ended_on)
                        : 'ปัจจุบัน'
                }`}
            />

            <section
                aria-label="สรุปรอบ"
                className="overflow-hidden rounded-lg border bg-card"
            >
                <div className="bg-brand-soft px-4 py-4">
                    <p className="text-sm text-brand-ink">
                        {profit < 0 ? 'ขาดทุนรอบนี้' : 'กำไรรอบนี้'}
                    </p>
                    <p
                        className={cn(
                            'text-[32px] leading-tight font-semibold',
                            profit < 0 && 'text-destructive',
                        )}
                    >
                        {profit < 0 ? '−' : ''}
                        {formatBaht(Math.abs(profit))}{' '}
                        <span className="text-base font-normal text-muted-foreground">
                            บาท
                        </span>
                    </p>
                    <p className="text-sm text-muted-foreground">
                        ยอดขาย {formatBaht(shownSales)} − ค่าใช้จ่าย{' '}
                        {formatBaht(shownExpenses)} บาท
                    </p>
                </div>
                <div className="grid grid-cols-2 gap-px border-t bg-border lg:grid-cols-4">
                    <Figure
                        label="ผลผลิตรวม"
                        value={formatNumber(totalYieldKg)}
                        unit="กก."
                        detail={
                            totalFruitCount > 0
                                ? `${formatNumber(totalFruitCount, 0)} ผล`
                                : undefined
                        }
                    />
                    <Figure
                        label="ขายไป"
                        value={formatNumber(salesWeightKg)}
                        unit="กก."
                        detail={
                            averagePrice
                                ? `เฉลี่ย ${averagePrice} บาท/กก.`
                                : undefined
                        }
                    />
                    <Figure
                        label="ค่าใช้จ่าย"
                        value={formatBaht(shownExpenses)}
                        unit="บาท"
                    />
                    <Figure
                        label="การดูแล"
                        value={formatNumber(careRecordCount, 0)}
                        unit="ครั้ง"
                    />
                </div>
            </section>

            <div className="grid items-start gap-6 lg:grid-cols-2">
                <section className="space-y-3">
                    <h2 className="text-base font-medium">ผลผลิตแต่ละต้น</h2>
                    {treeYields.length === 0 ? (
                        <EmptyState>ยังไม่มีผลผลิตในรอบนี้</EmptyState>
                    ) : (
                        <ul className="divide-y rounded-lg border bg-card">
                            {treeYields.map((row) => (
                                <li key={row.treeId}>
                                    <Link
                                        href={showTree(row.treeId)}
                                        className="block px-4 py-3 transition-colors hover:bg-accent/60 active:bg-accent"
                                    >
                                        <div className="flex items-baseline justify-between gap-3">
                                            <span className="text-base font-medium">
                                                {row.code}
                                            </span>
                                            <span className="text-base">
                                                {formatNumber(row.weightKg)}{' '}
                                                <span className="text-sm text-muted-foreground">
                                                    กก.
                                                    {row.fruitCount > 0 &&
                                                        ` · ${formatNumber(row.fruitCount, 0)} ผล`}
                                                </span>
                                            </span>
                                        </div>
                                        {row.weightKg > 0 && (
                                            <div
                                                aria-hidden
                                                className="mt-2 h-1 rounded-full bg-primary"
                                                style={{
                                                    width: `${(row.weightKg / maxTreeYield) * 100}%`,
                                                }}
                                            />
                                        )}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="space-y-3">
                    <h2 className="text-base font-medium">การขาย</h2>
                    {sales.length === 0 ? (
                        <EmptyState>ยังไม่มีการขายในรอบนี้</EmptyState>
                    ) : (
                        <ul className="divide-y rounded-lg border bg-card">
                            {sales.map((sale) => (
                                <li
                                    key={sale.id}
                                    className="flex items-start justify-between gap-3 px-4 py-3"
                                >
                                    <div className="min-w-0">
                                        <p className="text-base font-medium">
                                            {formatThaiDate(sale.sold_on)}
                                        </p>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {sale.buyer_name ??
                                                'ไม่ระบุผู้ซื้อ'}
                                        </p>
                                    </div>
                                    <div className="shrink-0 text-right">
                                        <p className="text-base font-medium">
                                            {formatBaht(sale.amount)}{' '}
                                            <span className="text-sm font-normal text-muted-foreground">
                                                บาท
                                            </span>
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {formatNumber(sale.weight_kg)} กก.
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </div>
    );
}
