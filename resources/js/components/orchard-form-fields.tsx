import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Orchard } from '@/types/farm';

export default function OrchardFormFields({
    orchard,
    errors,
}: {
    orchard?: Orchard;
    errors: Partial<Record<'name' | 'location' | 'notes', string>>;
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="name">ชื่อสวน</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    defaultValue={orchard?.name}
                    placeholder="เช่น สวนบ้านล่าง"
                />
                <InputError message={errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="location">ที่ตั้ง</Label>
                <Input
                    id="location"
                    name="location"
                    defaultValue={orchard?.location ?? ''}
                />
                <InputError message={errors.location} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="notes">หมายเหตุ</Label>
                <Input
                    id="notes"
                    name="notes"
                    defaultValue={orchard?.notes ?? ''}
                />
                <InputError message={errors.notes} />
            </div>
        </>
    );
}
