import { Head, setLayoutProps } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { index as plotsIndex, show as plotShow } from '@/routes/plots';
import { cropCycleStageLabels, type Activity, type CropCycle, type Expense } from '@/types/farm';

type PageProps = {
    cropCycle: CropCycle;
    totalDirectCost: number;
    activityTypes: { id: number; name: string }[];
    expenseCategories: { id: number; name: string }[];
};

export default function CropCycleShow({ cropCycle, totalDirectCost }: PageProps) {
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

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title={cropCycle.label} />
            <div className="flex items-center justify-between">
                <Heading title={cropCycle.label} description={plotName} />
                <Badge variant="secondary">{cropCycleStageLabels[cropCycle.stage]}</Badge>
            </div>

            <Card className="p-4">
                <p className="text-muted-foreground text-sm">ต้นทุนตรงรวมของรอบนี้</p>
                <p className="text-2xl font-semibold">
                    {totalDirectCost.toLocaleString('th-TH', { minimumFractionDigits: 2 })} บาท
                </p>
            </Card>

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
                            </div>
                        ))}
                    </div>
                )}
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
                                <p className="font-medium">
                                    {Number(expense.amount).toLocaleString('th-TH', { minimumFractionDigits: 2 })} บาท
                                </p>
                            </div>
                        ))}
                    </div>
                )}
            </Card>
        </div>
    );
}
