import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import GradeController from '@/actions/App/Http/Controllers/Farm/GradeController';
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
import { index } from '@/routes/grades';
import type { FruitType, Grade } from '@/types/farm';

export default function GradesIndex({
    grades,
    fruitTypes,
}: {
    grades: Grade[];
    fruitTypes: Pick<FruitType, 'id' | 'name'>[];
}) {
    const [editingId, setEditingId] = useState<number | null>(null);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title="เกรดผลไม้" />
            <Heading title="เกรดผลไม้" description="กำหนดเกรดของผลไม้แต่ละชนิด เช่น AB ตกไซซ์ คละ" />

            <Card className="p-4">
                <Form
                    {...GradeController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="grid gap-3 sm:grid-cols-4 sm:items-end"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="fruit_type_id">ชนิดผลไม้</Label>
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
                                <Label htmlFor="name">ชื่อเกรด</Label>
                                <Input id="name" name="name" required placeholder="เช่น AB" />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="sort_order">ลำดับ</Label>
                                <Input id="sort_order" name="sort_order" type="number" min={0} defaultValue={0} />
                                <InputError message={errors.sort_order} />
                            </div>
                            <Button disabled={processing}>เพิ่ม</Button>
                        </>
                    )}
                </Form>
            </Card>

            <div className="grid gap-3">
                {grades.length === 0 && <p className="text-muted-foreground text-sm">ยังไม่มีเกรด</p>}
                {grades.map((grade) => (
                    <Card key={grade.id} className="p-4">
                        {editingId === grade.id ? (
                            <Form
                                {...GradeController.update.form(grade.id)}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setEditingId(null)}
                                className="grid gap-3 sm:grid-cols-4 sm:items-end"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor={`fruit_type_id-${grade.id}`}>ชนิดผลไม้</Label>
                                            <Select name="fruit_type_id" defaultValue={String(grade.fruit_type_id)}>
                                                <SelectTrigger id={`fruit_type_id-${grade.id}`}>
                                                    <SelectValue />
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
                                            <Label htmlFor={`name-${grade.id}`}>ชื่อเกรด</Label>
                                            <Input id={`name-${grade.id}`} name="name" required defaultValue={grade.name} />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor={`sort_order-${grade.id}`}>ลำดับ</Label>
                                            <Input id={`sort_order-${grade.id}`} name="sort_order" type="number" min={0} defaultValue={grade.sort_order} />
                                            <InputError message={errors.sort_order} />
                                        </div>
                                        <div className="flex gap-2">
                                            <Button disabled={processing}>บันทึก</Button>
                                            <Button type="button" variant="outline" onClick={() => setEditingId(null)}>ยกเลิก</Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        ) : (
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium">
                                        {grade.name}
                                        <span className="text-muted-foreground ml-2 text-sm">({grade.fruit_type?.name})</span>
                                    </p>
                                    <p className="text-muted-foreground text-sm">ลำดับ {grade.sort_order}</p>
                                </div>
                                <div className="flex gap-2">
                                    <Button variant="outline" onClick={() => setEditingId(grade.id)}>แก้ไข</Button>
                                    <Button
                                        variant="destructive"
                                        onClick={() => {
                                            if (confirm('ลบเกรดนี้?')) {
                                                router.delete(GradeController.destroy.url(grade.id));
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

GradesIndex.layout = {
    breadcrumbs: [{ title: 'เกรดผลไม้', href: index() }],
};
