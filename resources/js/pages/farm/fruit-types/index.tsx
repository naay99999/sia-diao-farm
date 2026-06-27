import { Form, Head, router } from '@inertiajs/react';
import FruitTypeController from '@/actions/App/Http/Controllers/Farm/FruitTypeController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/fruit-types';
import type { FruitType } from '@/types/farm';

export default function FruitTypesIndex({ fruitTypes }: { fruitTypes: FruitType[] }) {
    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title="ชนิดผลไม้" />
            <Heading title="ชนิดผลไม้" description="จัดการชนิดผลไม้ในฟาร์ม" />

            <Card className="p-4">
                <Form
                    {...FruitTypeController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="flex items-end gap-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="name">ชื่อชนิดผลไม้</Label>
                                <Input id="name" name="name" required placeholder="เช่น ทุเรียน" />
                                <InputError message={errors.name} />
                            </div>
                            <Button disabled={processing}>เพิ่ม</Button>
                        </>
                    )}
                </Form>
            </Card>

            <div className="grid gap-3">
                {fruitTypes.length === 0 && (
                    <p className="text-muted-foreground text-sm">ยังไม่มีชนิดผลไม้</p>
                )}
                {fruitTypes.map((type) => (
                    <Card key={type.id} className="flex items-center justify-between p-4">
                        <div>
                            <p className="font-medium">{type.name}</p>
                            <p className="text-muted-foreground text-sm">
                                {type.varieties_count ?? 0} พันธุ์
                            </p>
                        </div>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (confirm('ลบชนิดผลไม้นี้?')) {
                                    router.delete(FruitTypeController.destroy.url(type.id));
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

FruitTypesIndex.layout = {
    breadcrumbs: [{ title: 'ชนิดผลไม้', href: index() }],
};
