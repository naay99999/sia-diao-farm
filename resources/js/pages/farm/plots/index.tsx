import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { create, index, show } from '@/routes/plots';
import { cropCycleStageLabels, type Plot } from '@/types/farm';

export default function PlotsIndex({ plots }: { plots: Plot[] }) {
    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title="แปลงผลไม้" />
            <div className="flex items-center justify-between">
                <Heading title="แปลงผลไม้" description="จัดการแปลงในฟาร์ม" />
                <Button asChild>
                    <Link href={create()}>เพิ่มแปลง</Link>
                </Button>
            </div>

            <div className="grid gap-3 md:grid-cols-2">
                {plots.length === 0 && (
                    <p className="text-muted-foreground text-sm">ยังไม่มีแปลง — กดเพิ่มแปลงเพื่อเริ่มต้น</p>
                )}
                {plots.map((plot) => (
                    <Link key={plot.id} href={show(plot.id)}>
                        <Card className="p-4 transition hover:border-foreground/20">
                            <div className="flex items-start justify-between">
                                <div>
                                    <p className="font-medium">{plot.name}</p>
                                    <p className="text-muted-foreground text-sm">
                                        {plot.fruit_variety?.fruit_type?.name} · {plot.fruit_variety?.name}
                                    </p>
                                </div>
                                {plot.active_crop_cycle && (
                                    <Badge variant="secondary">
                                        {cropCycleStageLabels[plot.active_crop_cycle.stage]}
                                    </Badge>
                                )}
                            </div>
                            <div className="text-muted-foreground mt-3 flex gap-4 text-sm">
                                <span>{plot.tree_count} ต้น</span>
                                {plot.tree_age_years !== null && <span>อายุ {plot.tree_age_years} ปี</span>}
                                {plot.area_rai && <span>{plot.area_rai} ไร่</span>}
                            </div>
                        </Card>
                    </Link>
                ))}
            </div>
        </div>
    );
}

PlotsIndex.layout = {
    breadcrumbs: [{ title: 'แปลงผลไม้', href: index() }],
};
