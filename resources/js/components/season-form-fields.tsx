import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Season } from '@/types/farm';

export default function SeasonFormFields({
    season,
    errors,
}: {
    season?: Season;
    errors: Partial<
        Record<'name' | 'started_on' | 'ended_on' | 'notes', string>
    >;
}) {
    const suggestedName = `ฤดู ${new Date().getFullYear() + 543}`;

    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="season-name">ชื่อรอบ</Label>
                <Input
                    id="season-name"
                    name="name"
                    required
                    defaultValue={season?.name ?? suggestedName}
                />
                <InputError message={errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="season-started-on">วันที่เริ่ม</Label>
                <Input
                    id="season-started-on"
                    name="started_on"
                    type="date"
                    required
                    defaultValue={season?.started_on.slice(0, 10)}
                />
                <InputError message={errors.started_on} />
            </div>
            {season && (
                <div className="grid gap-2">
                    <Label htmlFor="season-ended-on">
                        วันที่สิ้นสุด (เว้นว่างถ้ายังไม่ปิดรอบ)
                    </Label>
                    <Input
                        id="season-ended-on"
                        name="ended_on"
                        type="date"
                        defaultValue={season.ended_on?.slice(0, 10) ?? ''}
                    />
                    <InputError message={errors.ended_on} />
                </div>
            )}
            <div className="grid gap-2">
                <Label htmlFor="season-notes">หมายเหตุ</Label>
                <textarea
                    id="season-notes"
                    name="notes"
                    rows={3}
                    defaultValue={season?.notes ?? ''}
                    className="flex w-full rounded-md border border-input bg-card px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm"
                />
                <InputError message={errors.notes} />
            </div>
        </>
    );
}
