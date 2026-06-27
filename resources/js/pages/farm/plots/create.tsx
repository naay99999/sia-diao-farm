import { Form, Head } from '@inertiajs/react';
import PlotController from '@/actions/App/Http/Controllers/Farm/PlotController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/plots';
import type { FruitVariety } from '@/types/farm';

export default function PlotCreate({ fruitVarieties }: { fruitVarieties: FruitVariety[] }) {
    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title="เพิ่มแปลง" />
            <Heading title="เพิ่มแปลง" description="บันทึกข้อมูลแปลงผลไม้" />

            <Card className="max-w-2xl p-4">
                <Form {...PlotController.store.form()} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">ชื่อแปลง</Label>
                                <Input id="name" name="name" required placeholder="เช่น แปลงทุเรียนทิศเหนือ" />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="fruit_variety_id">พันธุ์</Label>
                                <Select name="fruit_variety_id">
                                    <SelectTrigger id="fruit_variety_id">
                                        <SelectValue placeholder="เลือกพันธุ์" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {fruitVarieties.map((variety) => (
                                            <SelectItem key={variety.id} value={String(variety.id)}>
                                                {variety.fruit_type?.name} · {variety.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.fruit_variety_id} />
                            </div>
                            <div className="grid gap-2 sm:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="tree_count">จำนวนต้น</Label>
                                    <Input id="tree_count" name="tree_count" type="number" min={1} required />
                                    <InputError message={errors.tree_count} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="planted_at">วันที่ปลูก</Label>
                                    <Input id="planted_at" name="planted_at" type="date" />
                                    <InputError message={errors.planted_at} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="area_rai">พื้นที่ (ไร่)</Label>
                                    <Input id="area_rai" name="area_rai" type="number" step="0.01" min={0} />
                                    <InputError message={errors.area_rai} />
                                </div>
                            </div>
                            <Button disabled={processing}>บันทึก</Button>
                        </>
                    )}
                </Form>
            </Card>
        </div>
    );
}

PlotCreate.layout = {
    breadcrumbs: [
        { title: 'แปลงผลไม้', href: index() },
        { title: 'เพิ่มแปลง', href: index() },
    ],
};
