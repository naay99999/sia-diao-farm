import { Link } from '@inertiajs/react';
import { TreeMap } from '@/components/farm/tree-map';
import { formatNumber } from '@/lib/format';
import { formatThaiDate } from '@/lib/thai-date';
import { show as showOrchard } from '@/routes/orchards';
import type { OrchardCareLatest, OrchardOverview } from '@/types/farm';

function LatestCare({
    label,
    latest,
}: {
    label: string;
    latest: OrchardCareLatest | null;
}) {
    return (
        <div>
            <dt className="text-base text-muted-foreground md:text-sm">
                {label}ล่าสุด
            </dt>
            <dd className="text-lg md:text-base">
                {latest === null
                    ? 'ยังไม่มีบันทึก'
                    : `${formatThaiDate(latest.date)}${latest.product ? ` · ${latest.product}` : ''}`}
            </dd>
        </div>
    );
}

/**
 * One orchard on the overview: its tree map, this season's numbers and the latest fertilizer and pesticide.
 */
export function OrchardSummaryCard({ orchard }: { orchard: OrchardOverview }) {
    const { season } = orchard;

    return (
        <section className="min-w-0 space-y-4 rounded-lg border bg-card p-4 md:p-5">
            <div className="flex flex-wrap items-baseline justify-between gap-x-3">
                <h2 className="text-xl font-semibold md:text-lg">
                    <Link
                        href={showOrchard(orchard.id)}
                        className="underline-offset-4 hover:underline"
                    >
                        {orchard.name}
                    </Link>
                </h2>
                <span className="text-base text-muted-foreground md:text-sm">
                    {orchard.treeCount.toLocaleString('th-TH')} ต้น
                </span>
            </div>

            <TreeMap rows={orchard.layout} />

            {season === null ? (
                <p className="text-base text-muted-foreground md:text-sm">
                    ยังไม่มีรอบที่เปิดอยู่
                </p>
            ) : (
                <div className="space-y-2">
                    <p className="text-base text-muted-foreground md:text-sm">
                        {season.name}
                    </p>
                    <dl className="grid grid-cols-3 gap-2">
                        <div className="rounded-lg bg-brand-soft p-3 text-brand-ink">
                            <dt className="text-base md:text-sm">ผลผลิต</dt>
                            <dd className="text-xl font-semibold tabular-nums md:text-lg">
                                {formatNumber(season.yieldKg)}{' '}
                                <span className="text-base font-normal md:text-sm">
                                    กก.
                                </span>
                            </dd>
                        </div>
                        <div className="rounded-lg bg-brand-soft p-3 text-brand-ink">
                            <dt className="text-base md:text-sm">ยอดขาย</dt>
                            <dd className="text-xl font-semibold tabular-nums md:text-lg">
                                {formatNumber(season.salesTotal, 0)}{' '}
                                <span className="text-base font-normal md:text-sm">
                                    บาท
                                </span>
                            </dd>
                        </div>
                        <div className="rounded-lg bg-muted p-3">
                            <dt className="text-base text-muted-foreground md:text-sm">
                                ค่าใช้จ่าย
                            </dt>
                            <dd className="text-xl font-semibold tabular-nums md:text-lg">
                                {formatNumber(season.expensesTotal, 0)}{' '}
                                <span className="text-base font-normal md:text-sm">
                                    บาท
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>
            )}

            <dl className="grid gap-3 sm:grid-cols-2">
                <LatestCare label="ใส่ปุ๋ย" latest={orchard.latestFertilizer} />
                <LatestCare label="ฉีดยา" latest={orchard.latestPesticide} />
            </dl>
        </section>
    );
}
