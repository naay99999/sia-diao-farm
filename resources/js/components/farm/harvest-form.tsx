import { todayIso } from '@/components/farm/care-record-form';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Harvest } from '@/types/farm';

type Errors = Record<string, string | undefined>;

export function HarvestFields({
    harvest,
    errors,
}: {
    harvest?: Harvest;
    errors: Errors;
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="harvested_on">วันที่เก็บ</Label>
                <Input
                    id="harvested_on"
                    name="harvested_on"
                    type="date"
                    required
                    defaultValue={
                        harvest?.harvested_on.slice(0, 10) ?? todayIso()
                    }
                />
                <InputError message={errors.harvested_on} />
            </div>
            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="weight_kg">น้ำหนัก (กก.)</Label>
                    <Input
                        id="weight_kg"
                        name="weight_kg"
                        inputMode="decimal"
                        required
                        defaultValue={harvest?.weight_kg ?? ''}
                        className="h-12 text-base"
                    />
                    <InputError message={errors.weight_kg} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="fruit_count">จำนวนผล</Label>
                    <Input
                        id="fruit_count"
                        name="fruit_count"
                        inputMode="numeric"
                        defaultValue={harvest?.fruit_count ?? ''}
                        className="h-12 text-base"
                    />
                    <InputError message={errors.fruit_count} />
                </div>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="harvest-notes">หมายเหตุ</Label>
                <textarea
                    id="harvest-notes"
                    name="notes"
                    rows={3}
                    defaultValue={harvest?.notes ?? ''}
                    className="flex w-full rounded-md border border-input bg-card px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                />
                <InputError message={errors.notes} />
            </div>
            <InputError message={errors.tree} />
        </>
    );
}
