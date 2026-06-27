import { Form, Head, router, setLayoutProps } from '@inertiajs/react';
import { useRef, useState } from 'react';
import ActivityController, { store as activityStore } from '@/actions/App/Http/Controllers/Farm/ActivityController';
import ExpenseController, { storeForCycle as expenseStoreForCycle } from '@/actions/App/Http/Controllers/Farm/ExpenseController';
import HarvestController, { store as harvestStore } from '@/actions/App/Http/Controllers/Farm/HarvestController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
import { index as plotsIndex, show as plotShow } from '@/routes/plots';
import { cropCycleStageLabels, type Activity, type CropCycle, type Expense, type Harvest, type Sale } from '@/types/farm';

type PageProps = {
    cropCycle: CropCycle;
    totalDirectCost: number;
    totalYield: number;
    revenue: number;
    costPerKg: number | null;
    profit: number;
    activityTypes: { id: number; name: string }[];
    expenseCategories: { id: number; name: string }[];
    grades: { id: number; name: string }[];
};

export default function CropCycleShow({ cropCycle, totalDirectCost, totalYield, revenue, costPerKg, profit, activityTypes, expenseCategories, grades }: PageProps) {
    const plotName = cropCycle.plot?.name ?? 'แปลง';

    setLayoutProps({
        breadcrumbs: [
            { title: 'แปลงผลไม้', href: plotsIndex() },
            { title: plotName, href: plotShow(cropCycle.plot_id) },
            { title: cropCycle.label, href: plotShow(cropCycle.plot_id) },
        ],
    });

    const activities: Activity[] = cropCycle.activities ?? [];
    const expenses: Expense[] = cropCycle.expenses ?? [];
    const harvests: Harvest[] = cropCycle.harvests ?? [];
    const sales: Sale[] = cropCycle.sales ?? [];
    const formatBaht = (value: number) => value.toLocaleString('th-TH', { minimumFractionDigits: 2 });

    const [harvestRowIds, setHarvestRowIds] = useState<number[]>([0]);
    const nextHarvestRowId = useRef(1);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title={cropCycle.label} />
            <div className="flex items-center justify-between">
                <Heading title={cropCycle.label} description={plotName} />
                <Badge variant="secondary">{cropCycleStageLabels[cropCycle.stage]}</Badge>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <Card className="p-4">
                    <p className="text-muted-foreground text-sm">ผลผลิตรวม</p>
                    <p className="text-2xl font-semibold">{totalYield.toLocaleString('th-TH')} กก.</p>
                </Card>
                <Card className="p-4">
                    <p className="text-muted-foreground text-sm">รายรับ</p>
                    <p className="text-2xl font-semibold">{formatBaht(revenue)} บาท</p>
                </Card>
                <Card className="p-4">
                    <p className="text-muted-foreground text-sm">ต้นทุนตรง</p>
                    <p className="text-2xl font-semibold">{formatBaht(totalDirectCost)} บาท</p>
                </Card>
                <Card className="p-4">
                    <p className="text-muted-foreground text-sm">ต้นทุน/กก.</p>
                    <p className="text-2xl font-semibold">{costPerKg !== null ? `${formatBaht(costPerKg)} บาท` : '—'}</p>
                </Card>
                <Card className="p-4">
                    <p className="text-muted-foreground text-sm">กำไร</p>
                    <p className={`text-2xl font-semibold ${profit >= 0 ? 'text-emerald-600' : 'text-red-600'}`}>{formatBaht(profit)} บาท</p>
                </Card>
            </div>

            <Card className="p-4">
                <p className="mb-3 font-medium">กิจกรรม</p>
                {activities.length === 0 ? (
                    <p className="text-muted-foreground text-sm">ยังไม่มีกิจกรรม</p>
                ) : (
                    <div className="grid gap-2">
                        {activities.map((activity) => (
                            <div key={activity.id} className="flex items-center justify-between rounded-md border p-3">
                                <div>
                                    <p className="font-medium">{activity.activity_type?.name}</p>
                                    <p className="text-muted-foreground text-sm">
                                        {activity.performed_on}
                                        {activity.notes ? ` · ${activity.notes}` : ''}
                                    </p>
                                </div>
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    onClick={() => {
                                        if (confirm('ลบกิจกรรมนี้?')) {
                                            router.delete(ActivityController.destroy.url(activity.id));
                                        }
                                    }}
                                >
                                    ลบ
                                </Button>
                            </div>
                        ))}
                    </div>
                )}

                <div className="mt-4 border-t pt-4">
                    <p className="mb-3 text-sm font-medium">บันทึกกิจกรรมใหม่</p>
                    <Form
                        action={activityStore.url(cropCycle.id)}
                        method="post"
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="grid gap-3 sm:grid-cols-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="activity_type_id">ประเภทกิจกรรม</Label>
                                    <Select name="activity_type_id">
                                        <SelectTrigger id="activity_type_id">
                                            <SelectValue placeholder="เลือกประเภท" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {activityTypes.map((type) => (
                                                <SelectItem key={type.id} value={String(type.id)}>
                                                    {type.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.activity_type_id} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="performed_on">วันที่ทำ</Label>
                                    <Input id="performed_on" name="performed_on" type="date" required />
                                    <InputError message={errors.performed_on} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="notes">รายละเอียด</Label>
                                    <Input id="notes" name="notes" placeholder="เช่น ปุ๋ยสูตร 15-15-15 2 กระสอบ" />
                                    <InputError message={errors.notes} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="expense_category_id">หมวดค่าใช้จ่าย (ถ้ามี)</Label>
                                    <Select name="expense_category_id">
                                        <SelectTrigger id="expense_category_id">
                                            <SelectValue placeholder="ไม่บันทึกค่าใช้จ่าย" />
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
                                    <Label htmlFor="cost">ค่าใช้จ่าย (บาท)</Label>
                                    <Input id="cost" name="cost" type="number" step="0.01" min={0} placeholder="0.00" />
                                    <InputError message={errors.cost} />
                                </div>
                                <div className="sm:col-span-2">
                                    <Button disabled={processing}>บันทึกกิจกรรม</Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </Card>

            <Card className="p-4">
                <p className="mb-3 font-medium">ค่าใช้จ่ายของรอบนี้</p>
                {expenses.length === 0 ? (
                    <p className="text-muted-foreground text-sm">ยังไม่มีค่าใช้จ่าย</p>
                ) : (
                    <div className="grid gap-2">
                        {expenses.map((expense) => (
                            <div key={expense.id} className="flex items-center justify-between rounded-md border p-3">
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
                            </div>
                        ))}
                    </div>
                )}

                <div className="mt-4 border-t pt-4">
                    <p className="mb-3 text-sm font-medium">บันทึกค่าใช้จ่ายของรอบนี้</p>
                    <Form
                        action={expenseStoreForCycle.url(cropCycle.id)}
                        method="post"
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="grid gap-3 sm:grid-cols-4 sm:items-end"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="expense_category_id_direct">หมวด</Label>
                                    <Select name="expense_category_id">
                                        <SelectTrigger id="expense_category_id_direct">
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
                                    <Label htmlFor="amount_direct">จำนวนเงิน (บาท)</Label>
                                    <Input id="amount_direct" name="amount" type="number" step="0.01" min={0.01} required />
                                    <InputError message={errors.amount} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="spent_on_direct">วันที่จ่าย</Label>
                                    <Input id="spent_on_direct" name="spent_on" type="date" required />
                                    <InputError message={errors.spent_on} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="description_direct">รายละเอียด</Label>
                                    <Input id="description_direct" name="description" placeholder="เช่น ค่าแรงเก็บผลผลิต" />
                                    <InputError message={errors.description} />
                                </div>
                                <div className="sm:col-span-4">
                                    <Button disabled={processing}>บันทึกค่าใช้จ่าย</Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </Card>

            <Card className="p-4">
                <p className="mb-3 font-medium">การเก็บเกี่ยว</p>
                {harvests.length === 0 ? (
                    <p className="text-muted-foreground text-sm">ยังไม่มีการเก็บเกี่ยว</p>
                ) : (
                    <div className="grid gap-2">
                        {harvests.map((harvest) => (
                            <div key={harvest.id} className="rounded-md border p-3">
                                <div className="flex items-center justify-between">
                                    <p className="font-medium">{harvest.harvested_on}</p>
                                    <Button
                                        size="sm"
                                        variant="destructive"
                                        onClick={() => {
                                            if (confirm('ลบการเก็บเกี่ยวนี้?')) {
                                                router.delete(HarvestController.destroy.url(harvest.id));
                                            }
                                        }}
                                    >
                                        ลบ
                                    </Button>
                                </div>
                                <div className="text-muted-foreground mt-1 grid gap-1 text-sm">
                                    {harvest.items?.map((item) => (
                                        <span key={item.id}>
                                            {item.grade?.name}: {Number(item.weight_kg).toLocaleString('th-TH')} กก.
                                        </span>
                                    ))}
                                </div>
                                {harvest.notes ? <p className="text-muted-foreground mt-1 text-sm">{harvest.notes}</p> : null}
                            </div>
                        ))}
                    </div>
                )}

                <div className="mt-4 border-t pt-4">
                    <p className="mb-3 text-sm font-medium">บันทึกการเก็บเกี่ยวใหม่</p>
                    <Form
                        action={harvestStore.url(cropCycle.id)}
                        method="post"
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        onSuccess={() => {
                            setHarvestRowIds([0]);
                            nextHarvestRowId.current = 1;
                        }}
                        className="grid gap-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-3 sm:grid-cols-2 sm:items-end">
                                    <div className="grid gap-2">
                                        <Label htmlFor="harvested_on">วันที่เก็บเกี่ยว</Label>
                                        <Input id="harvested_on" name="harvested_on" type="date" required />
                                        <InputError message={errors.harvested_on} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="harvest_notes">หมายเหตุ</Label>
                                        <Input id="harvest_notes" name="notes" placeholder="เช่น เก็บรอบแรก" />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label>ผลผลิตตามเกรด</Label>
                                    {harvestRowIds.map((rowId, idx) => (
                                        <div key={rowId} className="flex items-end gap-2">
                                            <div className="grid flex-1 gap-1">
                                                <Select name={`items[${idx}][grade_id]`}>
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="เลือกเกรด" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {grades.map((grade) => (
                                                            <SelectItem key={grade.id} value={String(grade.id)}>
                                                                {grade.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <InputError message={errors[`items.${idx}.grade_id`]} />
                                            </div>
                                            <div className="grid flex-1 gap-1">
                                                <Input
                                                    name={`items[${idx}][weight_kg]`}
                                                    type="number"
                                                    step="0.01"
                                                    min={0.01}
                                                    placeholder="น้ำหนัก (กก.)"
                                                    required
                                                />
                                                <InputError message={errors[`items.${idx}.weight_kg`]} />
                                            </div>
                                            {harvestRowIds.length > 1 && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => setHarvestRowIds((ids) => ids.filter((id) => id !== rowId))}
                                                >
                                                    ลบ
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                                    <InputError message={errors.items} />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="w-fit"
                                        onClick={() => setHarvestRowIds((ids) => [...ids, nextHarvestRowId.current++])}
                                    >
                                        + เพิ่มเกรด
                                    </Button>
                                </div>
                                <div>
                                    <Button disabled={processing}>บันทึกการเก็บเกี่ยว</Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </Card>

            <Card className="p-4">
                <p className="mb-3 font-medium">การขาย</p>
                {sales.length === 0 ? (
                    <p className="text-muted-foreground text-sm">ยังไม่มีการขาย</p>
                ) : (
                    <div className="grid gap-2">
                        {sales.map((sale) => (
                            <div key={sale.id} className="rounded-md border p-3">
                                <div className="flex items-center justify-between">
                                    <p className="font-medium">{sale.buyer_name}</p>
                                    <p className="text-muted-foreground text-sm">{sale.sold_on}</p>
                                </div>
                                <div className="text-muted-foreground mt-1 grid gap-1 text-sm">
                                    {sale.items?.map((item) => (
                                        <span key={item.id}>
                                            {item.grade?.name}: {Number(item.weight_kg).toLocaleString('th-TH')} กก. ×{' '}
                                            {formatBaht(Number(item.price_per_kg))} = {formatBaht(Number(item.subtotal))} บาท
                                        </span>
                                    ))}
                                </div>
                                {sale.notes ? <p className="text-muted-foreground mt-1 text-sm">{sale.notes}</p> : null}
                            </div>
                        ))}
                    </div>
                )}
            </Card>
        </div>
    );
}
