import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { RecordFilterOptions, RecordFilterValues } from '@/types/farm';

const selectClass =
    'flex h-12 w-full rounded-md border border-input bg-transparent px-3 py-1 text-lg shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:h-9 md:text-base';

/**
 * The filter bar every records tab shares. Each change reloads the current page with the new query, keeping scroll,
 * and starts again from the first page.
 */
export function RecordFilters({
    filters,
    orchards,
    seasons,
    members,
}: { filters: RecordFilterValues } & RecordFilterOptions) {
    const { url } = usePage();
    const path = url.split('?')[0];
    const isFiltered = Object.values(filters).some((value) => value !== null);

    const apply = (next: RecordFilterValues) => {
        if (next.from && next.to && next.to < next.from) {
            return;
        }

        const query = Object.fromEntries(
            Object.entries(next).filter(([, value]) => value !== null),
        );

        router.get(path, query, { preserveState: true, preserveScroll: true });
    };

    const change = (key: keyof RecordFilterValues, value: string) =>
        apply({ ...filters, [key]: value === '' ? null : value });

    return (
        <form
            aria-label="ตัวกรองบันทึก"
            onSubmit={(event) => event.preventDefault()}
            className="grid grid-cols-2 items-end gap-3 lg:grid-cols-4 xl:grid-cols-7"
        >
            <div className="grid gap-2">
                <Label htmlFor="filter-orchard">สวน</Label>
                <select
                    id="filter-orchard"
                    value={filters.orchard_id ?? ''}
                    onChange={(event) =>
                        change('orchard_id', event.target.value)
                    }
                    className={selectClass}
                >
                    <option value="">ทุกสวน</option>
                    {orchards.map((orchard) => (
                        <option key={orchard.id} value={orchard.id}>
                            {orchard.name}
                        </option>
                    ))}
                </select>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="filter-season">รอบการผลิต</Label>
                <select
                    id="filter-season"
                    value={filters.season_id ?? ''}
                    onChange={(event) =>
                        change('season_id', event.target.value)
                    }
                    className={selectClass}
                >
                    <option value="">ทุกรอบ</option>
                    {seasons.map((season) => (
                        <option key={season.id} value={season.id}>
                            {season.label}
                        </option>
                    ))}
                </select>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="filter-from">ตั้งแต่วันที่</Label>
                <Input
                    id="filter-from"
                    type="date"
                    value={filters.from ?? ''}
                    onChange={(event) => change('from', event.target.value)}
                />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="filter-to">ถึงวันที่</Label>
                <Input
                    id="filter-to"
                    type="date"
                    value={filters.to ?? ''}
                    onChange={(event) => change('to', event.target.value)}
                />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="filter-created-by">คนที่จด</Label>
                <select
                    id="filter-created-by"
                    value={filters.created_by ?? ''}
                    onChange={(event) =>
                        change('created_by', event.target.value)
                    }
                    className={selectClass}
                >
                    <option value="">ทุกคน</option>
                    {members.map((member) => (
                        <option key={member.id} value={member.id}>
                            {member.name}
                        </option>
                    ))}
                </select>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="filter-via">ที่มา</Label>
                <select
                    id="filter-via"
                    value={filters.via ?? ''}
                    onChange={(event) => change('via', event.target.value)}
                    className={selectClass}
                >
                    <option value="">ทุกที่มา</option>
                    <option value="app">แอป</option>
                    <option value="mcp">Claude</option>
                </select>
            </div>
            {isFiltered && (
                <Button
                    type="button"
                    variant="ghost"
                    onClick={() =>
                        router.get(
                            path,
                            {},
                            { preserveState: true, preserveScroll: true },
                        )
                    }
                >
                    ล้างตัวกรอง
                </Button>
            )}
        </form>
    );
}
