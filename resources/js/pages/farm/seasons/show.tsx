import { Head, Link, setLayoutProps } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Card } from '@/components/ui/card';
import { formatThaiDate } from '@/lib/thai-date';
import { index, show } from '@/routes/orchards';
import { show as showSeason } from '@/routes/seasons';
import { show as showTree } from '@/routes/trees';
import type { Orchard, Sale, Season, TreeSeasonYield } from '@/types/farm';

const number = (value: number) => value.toLocaleString('th-TH');

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <Card className="p-4">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="text-xl font-semibold">{value}</p>
        </Card>
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

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title={season.name} />
            <Heading
                title={season.name}
                description={`${orchard.name} · ${formatThaiDate(season.started_on)} - ${
                    season.ended_on ? formatThaiDate(season.ended_on) : 'ปัจจุบัน'
                }`}
            />

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Stat label="ผลผลิตรวม" value={`${number(totalYieldKg)} กก.`} />
                <Stat label="จำนวนผล" value={`${number(totalFruitCount)} ผล`} />
                <Stat
                    label="ยอดขาย"
                    value={`${number(salesTotal)} บาท (${number(salesWeightKg)} กก.)`}
                />
                <Stat label="ค่าใช้จ่าย" value={`${number(expensesTotal)} บาท`} />
                <Stat label="การดูแล" value={`${number(careRecordCount)} ครั้ง`} />
            </div>

            <section className="space-y-3">
                <h2 className="text-lg font-medium">ผลผลิตแต่ละต้น</h2>
                {treeYields.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        ยังไม่มีผลผลิตในรอบนี้
                    </p>
                )}
                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    {treeYields.map((row) => (
                        <Link
                            key={row.treeId}
                            href={showTree(row.treeId)}
                            className="flex items-center justify-between rounded-lg border p-3 transition hover:border-foreground/20"
                        >
                            <span className="font-medium">{row.code}</span>
                            <span className="text-sm text-muted-foreground">
                                {number(row.weightKg)} กก.
                                {row.fruitCount > 0 &&
                                    ` · ${number(row.fruitCount)} ผล`}
                            </span>
                        </Link>
                    ))}
                </div>
            </section>

            <section className="space-y-3">
                <h2 className="text-lg font-medium">การขาย</h2>
                {sales.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        ยังไม่มีการขายในรอบนี้
                    </p>
                )}
                <div className="space-y-2">
                    {sales.map((sale) => (
                        <div
                            key={sale.id}
                            className="flex items-center justify-between rounded-lg border p-3 text-sm"
                        >
                            <div>
                                <p className="font-medium">
                                    {formatThaiDate(sale.sold_on)}
                                </p>
                                {sale.buyer_name && (
                                    <p className="text-muted-foreground">
                                        {sale.buyer_name}
                                    </p>
                                )}
                            </div>
                            <div className="text-right">
                                <p>{number(Number(sale.amount))} บาท</p>
                                <p className="text-muted-foreground">
                                    {number(Number(sale.weight_kg))} กก.
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            </section>
        </div>
    );
}
