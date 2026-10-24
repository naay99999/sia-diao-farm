import { Form, Head, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import ExpenseController from '@/actions/App/Http/Controllers/Farm/ExpenseController';
import { useConfirm } from '@/components/confirm-dialog';
import { todayIso } from '@/components/farm/care-record-form';
import { EmptyState } from '@/components/farm/empty-state';
import { RowActions } from '@/components/farm/row-actions';
import { ShareBar } from '@/components/farm/share-bar';
import { SubmitButton } from '@/components/farm/submit-button';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { PageContainer } from '@/components/page-container';
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
import { Textarea } from '@/components/ui/textarea';
import { formatBaht } from '@/lib/format';
import { formatThaiDate } from '@/lib/thai-date';
import { index } from '@/routes/expenses';
import type { Expense, ExpenseCategory } from '@/types/farm';

type Errors = Record<string, string | undefined>;

type CategoryOption = { value: ExpenseCategory; label: string };

const controlClass =
    'flex h-12 w-full rounded-md border border-input bg-transparent px-3 py-1 text-lg shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:h-9 md:text-base';

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
                <Label htmlFor="expense-season">รอบการผลิต</Label>
                <select
                    id="expense-season"
                    name="season_id"
                    defaultValue={expense?.season_id ?? ''}
                    className={controlClass}
                >
                    <option value="">ไม่ผูกกับรอบ</option>
                    {seasons.map((season) => (
                        <option key={season.id} value={season.id}>
                            {season.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.season_id} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="expense-notes">หมายเหตุ</Label>
                <Textarea
                    id="expense-notes"
                    name="notes"
                    rows={3}
                    defaultValue={expense?.notes ?? ''}
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
    const confirm = useConfirm();
    const [dialog, setDialog] = useState<Expense | 'new' | null>(null);
    const categoryLabels = Object.fromEntries(
        categories.map((category) => [category.value, category.label]),
    );
    const isFiltered = Boolean(filters.from || filters.to);
    const largestCategory = Math.max(
        0,
        ...totalsByCategory.map((row) => Number(row.total)),
    );

    setLayoutProps({
        breadcrumbs: [{ title: 'ค่าใช้จ่าย', href: index() }],
    });

    return (
        <PageContainer>
            <Head title="ค่าใช้จ่าย" />
            <div className="flex items-start justify-between gap-3">
                <Heading
                    title="ค่าใช้จ่าย"
                    description={
                        isFiltered
                            ? `${filters.from ? formatThaiDate(filters.from) : 'แรกสุด'} – ${filters.to ? formatThaiDate(filters.to) : 'วันนี้'}`
                            : 'รายจ่ายทั้งหมดของฟาร์ม'
                    }
                />
                <Button onClick={() => setDialog('new')}>
                    บันทึกค่าใช้จ่าย
                </Button>
            </div>

            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <section
                    aria-label="สรุปค่าใช้จ่าย"
                    className="overflow-hidden rounded-lg border bg-card lg:sticky lg:top-6 lg:col-start-2 lg:row-start-1"
                >
                    <div className="bg-muted px-4 py-4">
                        <p className="text-base text-muted-foreground md:text-sm">
                            {isFiltered ? 'รวมในช่วงที่เลือก' : 'รวมทั้งหมด'}
                        </p>
                        <p className="text-3xl leading-[1.3] font-semibold">
                            {formatBaht(total)}{' '}
                            <span className="text-lg font-normal text-muted-foreground md:text-base">
                                บาท
                            </span>
                        </p>
                        <p className="text-base text-muted-foreground md:text-sm">
                            {expenses.length} รายการ
                        </p>
                    </div>
                    {totalsByCategory.length > 0 && (
                        <ul className="space-y-3 px-4 py-4">
                            {totalsByCategory.map((row) => (
                                <li key={row.category}>
                                    <div className="flex items-baseline justify-between gap-2 text-base md:text-sm">
                                        <span>{row.label}</span>
                                        <span>{formatBaht(row.total)}</span>
                                    </div>
                                    <ShareBar
                                        value={Number(row.total)}
                                        max={largestCategory}
                                        className="mt-1"
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <div className="min-w-0 space-y-4 lg:col-start-1 lg:row-start-1">
                    <form
                        method="get"
                        action={index.url()}
                        className="grid grid-cols-2 items-end gap-2 sm:flex sm:flex-wrap sm:gap-3"
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
                        <Button
                            type="submit"
                            variant="outline"
                            className="col-span-2 sm:col-auto"
                        >
                            กรองตามวันที่
                        </Button>
                        {isFiltered && (
                            <Button
                                type="button"
                                variant="ghost"
                                className="col-span-2 sm:col-auto"
                                onClick={() => router.get(index.url())}
                            >
                                ล้างตัวกรอง
                            </Button>
                        )}
                    </form>

                    {expenses.length === 0 ? (
                        <EmptyState>
                            {isFiltered
                                ? 'ไม่มีค่าใช้จ่ายในช่วงวันที่นี้'
                                : 'ยังไม่มีค่าใช้จ่าย'}
                        </EmptyState>
                    ) : (
                        <ul className="divide-y rounded-lg border bg-card">
                            {expenses.map((expense) => (
                                <li
                                    key={expense.id}
                                    className="flex items-center gap-3 py-3 pr-3 pl-4"
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-baseline justify-between gap-3">
                                            <p className="truncate text-lg font-medium md:text-base">
                                                {
                                                    categoryLabels[
                                                        expense.category
                                                    ]
                                                }
                                            </p>
                                            <p className="shrink-0 text-lg font-medium md:text-base">
                                                {formatBaht(expense.amount)}{' '}
                                                <span className="text-base font-normal text-muted-foreground md:text-sm">
                                                    บาท
                                                </span>
                                            </p>
                                        </div>
                                        <p className="truncate text-base text-muted-foreground md:text-sm">
                                            {formatThaiDate(expense.spent_on)}
                                            {expense.season &&
                                                ` · ${expense.season.orchard.name} · ${expense.season.name}`}
                                            {expense.notes &&
                                                ` · ${expense.notes}`}
                                        </p>
                                    </div>
                                    <RowActions
                                        label={`${categoryLabels[expense.category]} ${formatThaiDate(expense.spent_on)}`}
                                        onEdit={() => setDialog(expense)}
                                        onDelete={async () => {
                                            if (
                                                await confirm({
                                                    title: 'ลบค่าใช้จ่ายนี้?',
                                                    description: `${categoryLabels[expense.category]} ${formatBaht(expense.amount)} บาท วันที่ ${formatThaiDate(expense.spent_on)}`,
                                                    confirmLabel: 'ลบ',
                                                    destructive: true,
                                                })
                                            ) {
                                                router.delete(
                                                    ExpenseController.destroy.url(
                                                        expense.id,
                                                    ),
                                                );
                                            }
                                        }}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>

            <Dialog
                open={dialog !== null}
                onOpenChange={(open) => !open && setDialog(null)}
            >
                <DialogContent>
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
                                    <SubmitButton processing={processing} />
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </PageContainer>
    );
}
