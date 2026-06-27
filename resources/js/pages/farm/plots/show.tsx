import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import PlotController from '@/actions/App/Http/Controllers/Farm/PlotController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { edit, index, show } from '@/routes/plots';
import { cropCycleStageLabels, type Plot } from '@/types/farm';

export default function PlotShow({ plot }: { plot: Plot }) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'แปลงผลไม้', href: index() },
            { title: plot.name, href: show(plot.id) },
        ],
    });

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title={plot.name} />
            <div className="flex items-center justify-between">
                <Heading
                    title={plot.name}
                    description={`${plot.fruit_variety?.fruit_type?.name} · ${plot.fruit_variety?.name}`}
                />
                <div className="flex gap-2">
                    <Button asChild variant="outline">
                        <Link href={edit(plot.id)}>แก้ไข</Link>
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={() => {
                            if (confirm('ลบแปลงนี้?')) {
                                router.delete(PlotController.destroy.url(plot.id));
                            }
                        }}
                    >
                        ลบ
                    </Button>
                </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-3">
                <Card className="p-4">
                    <p className="text-muted-foreground text-sm">จำนวนต้น</p>
                    <p className="text-2xl font-semibold">{plot.tree_count}</p>
                </Card>
                <Card className="p-4">
                    <p className="text-muted-foreground text-sm">อายุต้นไม้</p>
                    <p className="text-2xl font-semibold">
                        {plot.tree_age_years !== null ? `${plot.tree_age_years} ปี` : '—'}
                    </p>
                </Card>
                <Card className="p-4">
                    <p className="text-muted-foreground text-sm">พื้นที่</p>
                    <p className="text-2xl font-semibold">{plot.area_rai ? `${plot.area_rai} ไร่` : '—'}</p>
                </Card>
            </div>

            <Card className="p-4">
                <p className="mb-3 font-medium">รอบการผลิต</p>
                {(plot.crop_cycles?.length ?? 0) === 0 ? (
                    <p className="text-muted-foreground text-sm">ยังไม่มีรอบการผลิต</p>
                ) : (
                    <div className="grid gap-2">
                        {plot.crop_cycles?.map((cycle) => (
                            <div key={cycle.id} className="rounded-md border p-3">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="font-medium">{cycle.label}</p>
                                        <p className="text-muted-foreground text-sm">
                                            {cycle.expected_harvest_date
                                                ? `คาดเก็บเกี่ยว ${cycle.expected_harvest_date}`
                                                : 'ยังไม่บันทึกวันดอกบาน'}
                                        </p>
                                    </div>
                                    <Badge variant="secondary">{cropCycleStageLabels[cycle.stage]}</Badge>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </Card>
        </div>
    );
}

