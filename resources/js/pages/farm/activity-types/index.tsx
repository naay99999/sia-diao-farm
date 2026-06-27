import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import ActivityTypeController from '@/actions/App/Http/Controllers/Farm/ActivityTypeController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/activity-types';
import type { ActivityType } from '@/types/farm';

export default function ActivityTypesIndex({ activityTypes }: { activityTypes: ActivityType[] }) {
    const [editingId, setEditingId] = useState<number | null>(null);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title="ประเภทกิจกรรม" />
            <Heading title="ประเภทกิจกรรม" description="จัดการประเภทกิจกรรมในสวน เช่น ใส่ปุ๋ย พ่นยา" />

            <Card className="p-4">
                <Form
                    {...ActivityTypeController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="flex items-end gap-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="name">ชื่อประเภทกิจกรรม</Label>
                                <Input id="name" name="name" required placeholder="เช่น ใส่ปุ๋ย" />
                                <InputError message={errors.name} />
                            </div>
                            <Button disabled={processing}>เพิ่ม</Button>
                        </>
                    )}
                </Form>
            </Card>

            <div className="grid gap-3">
                {activityTypes.length === 0 && (
                    <p className="text-muted-foreground text-sm">ยังไม่มีประเภทกิจกรรม</p>
                )}
                {activityTypes.map((type) => (
                    <Card key={type.id} className="p-4">
                        {editingId === type.id ? (
                            <Form
                                {...ActivityTypeController.update.form(type.id)}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setEditingId(null)}
                                className="flex items-end gap-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid flex-1 gap-2">
                                            <Label htmlFor={`name-${type.id}`}>ชื่อประเภทกิจกรรม</Label>
                                            <Input
                                                id={`name-${type.id}`}
                                                name="name"
                                                required
                                                defaultValue={type.name}
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                        <Button disabled={processing}>บันทึก</Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setEditingId(null)}
                                        >
                                            ยกเลิก
                                        </Button>
                                    </>
                                )}
                            </Form>
                        ) : (
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium">{type.name}</p>
                                    <p className="text-muted-foreground text-sm">
                                        {type.activities_count ?? 0} กิจกรรม
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <Button variant="outline" onClick={() => setEditingId(type.id)}>
                                        แก้ไข
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        onClick={() => {
                                            if (confirm('ลบประเภทกิจกรรมนี้?')) {
                                                router.delete(
                                                    ActivityTypeController.destroy.url(type.id),
                                                );
                                            }
                                        }}
                                    >
                                        ลบ
                                    </Button>
                                </div>
                            </div>
                        )}
                    </Card>
                ))}
            </div>
        </div>
    );
}

ActivityTypesIndex.layout = {
    breadcrumbs: [{ title: 'ประเภทกิจกรรม', href: index() }],
};
