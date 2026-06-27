import { Form, Head, router } from '@inertiajs/react';
import ExpenseController, { store as expenseStore } from '@/actions/App/Http/Controllers/Farm/ExpenseController';
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
import { index } from '@/routes/expenses';
import type { Expense } from '@/types/farm';

type PageProps = {
    expenses: Expense[];
    expenseCategories: { id: number; name: string }[];
};

export default function ExpensesIndex({ expenses, expenseCategories }: PageProps) {
    const total = expenses.reduce((sum, expense) => sum + Number(expense.amount), 0);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title="ค่าใช้จ่ายส่วนกลาง" />
            <Heading title="ค่าใช้จ่ายส่วนกลาง" description="ค่าใช้จ่ายระดับฟาร์มที่ไม่ผูกกับรอบการผลิต เช่น ค่าน้ำมัน ค่าซ่อม" />

            <Card className="p-4">
                <Form {...expenseStore.form()} options={{ preserveScroll: true }} resetOnSuccess className="grid gap-3 sm:grid-cols-4 sm:items-end">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="expense_category_id">หมวด</Label>
                                <Select name="expense_category_id">
                                    <SelectTrigger id="expense_category_id">
                                        <SelectValue placeholder="เลือกหมวด" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {expenseCategories.map((category) => (
                                            <SelectItem key={category.id} value={String(category.id)}>
                                                {category.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.expense_category_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="amount">จำนวนเงิน (บาท)</Label>
                                <Input id="amount" name="amount" type="number" step="0.01" min={0.01} required />
                                <InputError message={errors.amount} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="spent_on">วันที่จ่าย</Label>
                                <Input id="spent_on" name="spent_on" type="date" required />
                                <InputError message={errors.spent_on} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="description">รายละเอียด</Label>
                                <Input id="description" name="description" placeholder="เช่น ค่าน้ำมันรถตัดหญ้า" />
                                <InputError message={errors.description} />
                            </div>
                            <div className="sm:col-span-4">
                                <Button disabled={processing}>บันทึก</Button>
                            </div>
                        </>
                    )}
                </Form>
            </Card>

            <Card className="p-4">
                <p className="text-muted-foreground text-sm">รวมค่าใช้จ่ายส่วนกลาง</p>
                <p className="text-2xl font-semibold">
                    {total.toLocaleString('th-TH', { minimumFractionDigits: 2 })} บาท
                </p>
            </Card>

            <div className="grid gap-3">
                {expenses.length === 0 && (
                    <p className="text-muted-foreground text-sm">ยังไม่มีค่าใช้จ่ายส่วนกลาง</p>
                )}
                {expenses.map((expense) => (
                    <Card key={expense.id} className="flex items-center justify-between p-4">
                        <div>
                            <p className="font-medium">{expense.expense_category?.name}</p>
                            <p className="text-muted-foreground text-sm">
                                {expense.spent_on}
                                {expense.description ? ` · ${expense.description}` : ''}
                            </p>
                        </div>
                        <div className="flex items-center gap-3">
                            <p className="font-medium">
                                {Number(expense.amount).toLocaleString('th-TH', { minimumFractionDigits: 2 })} บาท
                            </p>
                            <Button
                                size="sm"
                                variant="destructive"
                                onClick={() => {
                                    if (confirm('ลบค่าใช้จ่ายนี้?')) {
                                        router.delete(ExpenseController.destroy.url(expense.id));
                                    }
                                }}
                            >
                                ลบ
                            </Button>
                        </div>
                    </Card>
                ))}
            </div>
        </div>
    );
}

ExpensesIndex.layout = {
    breadcrumbs: [{ title: 'ค่าใช้จ่ายส่วนกลาง', href: index() }],
};
