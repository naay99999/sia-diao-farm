import { Form, Head, router } from '@inertiajs/react';
import FruitVarietyController from '@/actions/App/Http/Controllers/Farm/FruitVarietyController';
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
import { index } from '@/routes/fruit-varieties';
import type { FruitType, FruitVariety } from '@/types/farm';

export default function FruitVarietiesIndex({
    fruitVarieties,
    fruitTypes,
}: {
    fruitVarieties: FruitVariety[];
    fruitTypes: Pick<FruitType, 'id' | 'name'>[];
}) {
    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title="พันธุ์ผลไม้" />
            <Heading
                title="พันธุ์ผลไม้"
                description="กำหนดพันธุ์และจำนวนวันมาตรฐานจากดอกบานถึงเก็บเกี่ยว"
            />

            <Card className="p-4">
                <Form
                    {...FruitVarietyController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="grid gap-3 sm:grid-cols-4 sm:items-end"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="fruit_type_id">ชนิด</Label>
                                <Select name="fruit_type_id">
                                    <SelectTrigger id="fruit_type_id">
                                        <SelectValue placeholder="เลือกชนิด" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {fruitTypes.map((type) => (
                                            <SelectItem key={type.id} value={String(type.id)}>
                                                {type.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.fruit_type_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="name">ชื่อพันธุ์</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    placeholder="เช่น หมอนทอง"
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="days_to_harvest">วันถึงเก็บเกี่ยว</Label>
                                <Input
                                    id="days_to_harvest"
                                    name="days_to_harvest"
                                    type="number"
                                    min={1}
                                    required
                                    placeholder="135"
                                />
                                <InputError message={errors.days_to_harvest} />
                            </div>
                            <Button disabled={processing}>เพิ่ม</Button>
                        </>
                    )}
                </Form>
            </Card>

            <div className="grid gap-3">
                {fruitVarieties.length === 0 && (
                    <p className="text-muted-foreground text-sm">ยังไม่มีพันธุ์</p>
                )}
                {fruitVarieties.map((variety) => (
                    <Card key={variety.id} className="flex items-center justify-between p-4">
                        <div>
                            <p className="font-medium">
                                {variety.name}
                                <span className="text-muted-foreground ml-2 text-sm">
                                    ({variety.fruit_type?.name})
                                </span>
                            </p>
                            <p className="text-muted-foreground text-sm">
                                {variety.days_to_harvest} วันจากดอกบานถึงเก็บเกี่ยว
                            </p>
                        </div>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (confirm('ลบพันธุ์นี้?')) {
                                    router.delete(
                                        FruitVarietyController.destroy.url(variety.id),
                                    );
                                }
                            }}
                        >
                            ลบ
                        </Button>
                    </Card>
                ))}
            </div>
        </div>
    );
}

FruitVarietiesIndex.layout = {
    breadcrumbs: [{ title: 'พันธุ์ผลไม้', href: index() }],
};
