import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import ExpenseCategoryController from '@/actions/App/Http/Controllers/Farm/ExpenseCategoryController';
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
import { index } from '@/routes/expense-categories';
import { expenseScopeLabels, type ExpenseCategory, type ExpenseScope } from '@/types/farm';

const scopeOptions: ExpenseScope[] = ['direct', 'overhead'];

export default function ExpenseCategoriesIndex({
    expenseCategories,
}: {
    expenseCategories: ExpenseCategory[];
}) {
    const [editingId, setEditingId] = useState<number | null>(null);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title="หมวดค่าใช้จ่าย" />
            <Heading
                title="หมวดค่าใช้จ่าย"
                description="จัดการหมวดค่าใช้จ่าย เช่น ค่าปุ๋ย ค่าแรง พร้อมขอบเขตเริ่มต้น"
            />

            <Card className="p-4">
                <Form
                    {...ExpenseCategoryController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="grid gap-3 sm:grid-cols-3 sm:items-end"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">ชื่อหมวด</Label>
                                <Input id="name" name="name" required placeholder="เช่น ค่าปุ๋ย" />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="default_scope">ขอบเขตเริ่มต้น</Label>
                                <Select name="default_scope" defaultValue="direct">
                                    <SelectTrigger id="default_scope">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {scopeOptions.map((scope) => (
                                            <SelectItem key={scope} value={scope}>
                                                {expenseScopeLabels[scope]}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.default_scope} />
                            </div>
                            <Button disabled={processing}>เพิ่ม</Button>
                        </>
                    )}
                </Form>
            </Card>

            <div className="grid gap-3">
                {expenseCategories.length === 0 && (
                    <p className="text-muted-foreground text-sm">ยังไม่มีหมวดค่าใช้จ่าย</p>
                )}
                {expenseCategories.map((category) => (
                    <Card key={category.id} className="p-4">
                        {editingId === category.id ? (
                            <Form
                                {...ExpenseCategoryController.update.form(category.id)}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setEditingId(null)}
                                className="grid gap-3 sm:grid-cols-3 sm:items-end"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor={`name-${category.id}`}>ชื่อหมวด</Label>
                                            <Input
                                                id={`name-${category.id}`}
                                                name="name"
                                                required
                                                defaultValue={category.name}
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor={`default_scope-${category.id}`}>
                                                ขอบเขตเริ่มต้น
                                            </Label>
                                            <Select
                                                name="default_scope"
                                                defaultValue={category.default_scope}
                                            >
                                                <SelectTrigger
                                                    id={`default_scope-${category.id}`}
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {scopeOptions.map((scope) => (
                                                        <SelectItem key={scope} value={scope}>
                                                            {expenseScopeLabels[scope]}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.default_scope} />
                                        </div>
                                        <div className="flex gap-2">
                                            <Button disabled={processing}>บันทึก</Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => setEditingId(null)}
                                            >
                                                ยกเลิก
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        ) : (
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium">{category.name}</p>
                                    <p className="text-muted-foreground text-sm">
                                        {expenseScopeLabels[category.default_scope]}
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        onClick={() => setEditingId(category.id)}
                                    >
                                        แก้ไข
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        onClick={() => {
                                            if (confirm('ลบหมวดค่าใช้จ่ายนี้?')) {
                                                router.delete(
                                                    ExpenseCategoryController.destroy.url(
                                                        category.id,
                                                    ),
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

ExpenseCategoriesIndex.layout = {
    breadcrumbs: [{ title: 'หมวดค่าใช้จ่าย', href: index() }],
};
