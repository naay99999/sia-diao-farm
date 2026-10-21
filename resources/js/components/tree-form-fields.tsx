import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Tree } from '@/types/farm';

export default function TreeFormFields({
    tree,
    errors,
}: {
    tree?: Tree;
    errors: Partial<
        Record<'code' | 'variety' | 'planted_on' | 'notes', string>
    >;
}) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="code">รหัสต้น</Label>
                <Input
                    id="code"
                    name="code"
                    required
                    defaultValue={tree?.code}
                    placeholder="เช่น A-001"
                />
                <InputError message={errors.code} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="variety">พันธุ์</Label>
                <Input
                    id="variety"
                    name="variety"
                    defaultValue={tree?.variety ?? ''}
                    placeholder="เช่น หมอนทอง"
                />
                <InputError message={errors.variety} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="planted_on">วันที่ปลูก</Label>
                <Input
                    id="planted_on"
                    name="planted_on"
                    type="date"
                    defaultValue={tree?.planted_on?.slice(0, 10) ?? ''}
                />
                <InputError message={errors.planted_on} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="notes">บันทึก</Label>
                <textarea
                    id="notes"
                    name="notes"
                    rows={3}
                    defaultValue={tree?.notes ?? ''}
                    className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                />
                <InputError message={errors.notes} />
            </div>
        </>
    );
}
