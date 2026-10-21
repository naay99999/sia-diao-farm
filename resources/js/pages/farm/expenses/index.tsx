import { Form, Head, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import ExpenseController from '@/actions/App/Http/Controllers/Farm/ExpenseController';
import { todayIso } from '@/components/farm/care-record-form';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatThaiDate } from '@/lib/thai-date';
import { index } from '@/routes/expenses';
import type { Expense, ExpenseCategory } from '@/types/farm';

type Errors = Record<string, string | undefined>;

type CategoryOption = { value: ExpenseCategory; label: string };

const controlClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm';

const formatBaht = (value: number | string): string =>
    Number(value).toLocaleString('th-TH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

function ExpenseFields({
    expense,
    categories,
    seasons,
    errors,
}: {
    expense?: Expense;
    categories: CategoryOption[];
    seasons: { id: number; label: string }[];
    errors: Errors;
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="expense-category">หมวดหมู่</Label>
                <select
                    id="expense-category"
                    name="category"
                    required
                    defaultValue={expense?.category ?? 'fertilizer'}
                    className={controlClass}
                >
                    {categories.map((category) => (
                        <option key={category.value} value={category.value}>
                            {category.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.category} />
            </div>
            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="expense-spent-on">วันที่จ่าย</Label>
                    <Input
                        id="expense-spent-on"
                        name="spent_on"
                        type="date"
                        required
                        defaultValue={
                            expense?.spent_on.slice(0, 10) ?? todayIso()
                        }
                    />
                    <InputError message={errors.spent_on} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="expense-amount">จำนวนเงิน (บาท)</Label>
                    <Input
                        id="expense-amount"
                        name="amount"
                        inputMode="decimal"
                        required
                        defaultValue={expense?.amount ?? ''}
                    />
                    <InputError message={errors.amount} />
                </div>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="expense-season">ฤดูกาล</Label>
                <select
                    id="expense-season"
                    name="season_id"
                    defaultValue={expense?.season_id ?? ''}
                    className={controlClass}
                >
                    <option value="">ไม่ระบุ</option>
                    {seasons.map((season) => (
                        <option key={season.id} value={season.id}>
                            {season.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.season_id} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="expense-notes">บันทึก</Label>
                <textarea
                    id="expense-notes"
                    name="notes"
                    rows={3}
                    defaultValue={expense?.notes ?? ''}
                    className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                />
                <InputError message={errors.notes} />
            </div>
        </>
    );
}

export default function ExpensesIndex({
    expenses,
    total,
    totalsByCategory,
    filters,
    seasons,
    categories,
}: {
    expenses: Expense[];
    total: number;
    totalsByCategory: {
        category: ExpenseCategory;
        label: string;
        total: number;
    }[];
    filters: { from: string | null; to: string | null };
    seasons: { id: number; label: string }[];
    categories: CategoryOption[];
}) {
    const [dialog, setDialog] = useState<Expense | 'new' | null>(null);
    const categoryLabels = Object.fromEntries(
        categories.map((category) => [category.value, category.label]),
    );

    setLayoutProps({
        breadcrumbs: [{ title: 'ค่าใช้จ่าย', href: index() }],
    });

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title="ค่าใช้จ่าย" />
            <div className="flex items-center justify-between gap-2">
                <Heading
                    title="ค่าใช้จ่าย"
                    description="บันทึกรายจ่ายของฟาร์ม"
                />
                <Button onClick={() => setDialog('new')}>
                    บันทึกค่าใช้จ่าย
                </Button>
            </div>

            <form
                method="get"
                action={index.url()}
                className="flex flex-wrap items-end gap-3"
            >
                <div className="grid gap-2">
                    <Label htmlFor="filter-from">ตั้งแต่วันที่</Label>
                    <Input
                        id="filter-from"
                        name="from"
                        type="date"
                        defaultValue={filters.from ?? ''}
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="filter-to">ถึงวันที่</Label>
                    <Input
                        id="filter-to"
                        name="to"
                        type="date"
                        defaultValue={filters.to ?? ''}
                    />
                </div>
                <Button type="submit" variant="outline">
                    กรอง
                </Button>
                {(filters.from || filters.to) && (
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => router.get(index.url())}
                    >
                        ล้างตัวกรอง
                    </Button>
                )}
            </form>

            <section className="space-y-2 rounded-lg border p-4">
                <p className="text-sm text-muted-foreground">รวมทั้งหมด</p>
                <p className="text-2xl font-semibold">
                    {formatBaht(total)} บาท
                </p>
                {totalsByCategory.length > 0 && (
                    <ul className="grid gap-1 pt-2 text-sm">
                        {totalsByCategory.map((row) => (
                            <li
                                key={row.category}
                                className="flex justify-between gap-2"
                            >
                                <span>{row.label}</span>
                                <span>{formatBaht(row.total)} บาท</span>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            {expenses.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    ยังไม่มีค่าใช้จ่าย
                </p>
            )}
            <div className="grid gap-2">
                {expenses.map((expense) => (
                    <div
                        key={expense.id}
                        className="flex items-start justify-between gap-2 rounded-lg border p-3"
                    >
                        <div className="min-w-0">
                            <p className="font-medium">
                                {categoryLabels[expense.category]} ·{' '}
                                {formatBaht(expense.amount)} บาท
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {formatThaiDate(expense.spent_on)}
                                {expense.season &&
                                    ` · ${expense.season.orchard.name} · ${expense.season.name}`}
                            </p>
                            {expense.notes && (
                                <p className="text-sm text-muted-foreground">
                                    {expense.notes}
                                </p>
                            )}
                        </div>
                        <div className="flex shrink-0 gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setDialog(expense)}
                            >
                                แก้ไข
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={() => {
                                    if (confirm('ลบค่าใช้จ่ายนี้?')) {
                                        router.delete(
                                            ExpenseController.destroy.url(
                                                expense.id,
                                            ),
                                        );
                                    }
                                }}
                            >
                                ลบ
                            </Button>
                        </div>
                    </div>
                ))}
            </div>

            <Dialog
                open={dialog !== null}
                onOpenChange={(open) => !open && setDialog(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {dialog === 'new'
                                ? 'บันทึกค่าใช้จ่าย'
                                : 'แก้ไขค่าใช้จ่าย'}
                        </DialogTitle>
                        <DialogDescription>
                            เลือกหมวดหมู่และระบุจำนวนเงิน
                        </DialogDescription>
                    </DialogHeader>
                    {dialog && (
                        <Form
                            key={dialog === 'new' ? 'new' : dialog.id}
                            {...(dialog === 'new'
                                ? ExpenseController.store.form()
                                : ExpenseController.update.form(dialog.id))}
                            onSuccess={() => setDialog(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <ExpenseFields
                                        expense={
                                            dialog === 'new'
                                                ? undefined
                                                : dialog
                                        }
                                        categories={categories}
                                        seasons={seasons}
                                        errors={errors}
                                    />
                                    <Button type="submit" disabled={processing}>
                                        บันทึก
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
}
