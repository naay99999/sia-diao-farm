import { Form, Head, setLayoutProps } from '@inertiajs/react';
import HarvestController from '@/actions/App/Http/Controllers/Farm/HarvestController';
import { todayIso } from '@/components/farm/care-record-form';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/orchards';
import type { Orchard, Tree } from '@/types/farm';

export default function HarvestCreate({
    orchard,
    trees,
}: {
    orchard: Orchard;
    trees: Pick<Tree, 'id' | 'code' | 'variety'>[];
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'สวน', href: index() },
            { title: orchard.name, href: show(orchard.id) },
            { title: 'บันทึกผลผลิตทั้งสวน', href: '#' },
        ],
    });

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <Head title="บันทึกผลผลิตทั้งสวน" />
            <Heading
                title="บันทึกผลผลิตทั้งสวน"
                description={`${orchard.name} · กรอกเฉพาะต้นที่เก็บผลผลิต`}
            />
            {trees.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    ยังไม่มีต้นไม้ในสวนนี้
                </p>
            ) : (
                <Form
                    {...HarvestController.storeBatch.form(orchard.id)}
                    className="max-w-2xl space-y-4"
                >
                    {({ processing, errors }) => {
                        const rowsError = Object.entries(errors).find(
                            ([key]) => key === 'rows',
                        )?.[1];

                        return (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="harvested_on">
                                        วันที่เก็บ
                                    </Label>
                                    <Input
                                        id="harvested_on"
                                        name="harvested_on"
                                        type="date"
                                        required
                                        className="h-12 text-base"
                                        defaultValue={todayIso()}
                                    />
                                    <InputError message={errors.harvested_on} />
                                </div>
                                <div className="grid grid-cols-[1fr_6rem_6rem] items-center gap-2 text-sm text-muted-foreground">
                                    <span>ต้นไม้</span>
                                    <span>น้ำหนัก (กก.)</span>
                                    <span>จำนวนผล</span>
                                </div>
                                <div className="grid gap-2">
                                    {trees.map((tree, index) => (
                                        <div
                                            key={tree.id}
                                            className="grid grid-cols-[1fr_6rem_6rem] items-center gap-2"
                                        >
                                            <input
                                                type="hidden"
                                                name={`rows[${index}][tree_id]`}
                                                value={tree.id}
                                            />
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">
                                                    {tree.code}
                                                </p>
                                                {tree.variety && (
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {tree.variety}
                                                    </p>
                                                )}
                                            </div>
                                            <Input
                                                name={`rows[${index}][weight_kg]`}
                                                inputMode="decimal"
                                                aria-label={`น้ำหนัก (กก.) ${tree.code}`}
                                                className="h-12 text-base"
                                            />
                                            <Input
                                                name={`rows[${index}][fruit_count]`}
                                                inputMode="numeric"
                                                aria-label={`จำนวนผล ${tree.code}`}
                                                className="h-12 text-base"
                                            />
                                            <div className="col-span-3 empty:hidden">
                                                <InputError
                                                    message={
                                                        errors[
                                                            `rows.${index}.tree_id`
                                                        ] ??
                                                        errors[
                                                            `rows.${index}.weight_kg`
                                                        ] ??
                                                        errors[
                                                            `rows.${index}.fruit_count`
                                                        ]
                                                    }
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                <InputError message={rowsError} />
                                <div className="sticky bottom-0 -mx-4 border-t bg-background p-4">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="h-12 w-full text-base"
                                    >
                                        บันทึก
                                    </Button>
                                </div>
                            </>
                        );
                    }}
                </Form>
            )}
        </div>
    );
}
