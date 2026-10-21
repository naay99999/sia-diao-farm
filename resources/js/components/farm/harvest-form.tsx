import { todayIso } from '@/components/farm/care-record-form';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
                    />
                    <InputError message={errors.fruit_count} />
                </div>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="harvest-notes">หมายเหตุ</Label>
                <Textarea
                    id="harvest-notes"
                    name="notes"
                    rows={3}
                    defaultValue={harvest?.notes ?? ''}
                />
                <InputError message={errors.notes} />
            </div>
            <InputError message={errors.tree} />
        </>
    );
}
