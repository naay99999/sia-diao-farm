import { Link } from '@inertiajs/react';
import { recordOptionProps } from '@/components/farm/record-filters';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types/farm';

/**
 * The "showing x–y of n" line and previous/next buttons under a records list. Paging keeps the filter options
 * already loaded.
 */
export function RecordsPagination({
    records,
}: {
    records: Paginated<unknown>;
}) {
    if (records.total === 0) {
        return null;
    }

    const previous = records.links[0]?.url;
    const next = records.links[records.links.length - 1]?.url;

    return (
        <nav
            aria-label="เลือกหน้า"
            className="flex flex-wrap items-center justify-between gap-3"
        >
            <p className="text-base text-muted-foreground md:text-sm">
                แสดง {records.from?.toLocaleString('th-TH')}–
                {records.to?.toLocaleString('th-TH')} จาก{' '}
                {records.total.toLocaleString('th-TH')} รายการ
            </p>
            {records.last_page > 1 && (
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        disabled={!previous}
                        asChild={Boolean(previous)}
                    >
                        {previous ? (
                            <Link
                                href={previous}
                                preserveScroll
                                except={recordOptionProps}
                            >
                                ก่อนหน้า
                            </Link>
                        ) : (
                            'ก่อนหน้า'
                        )}
                    </Button>
                    <span className="text-base md:text-sm">
                        หน้า {records.current_page} จาก {records.last_page}
                    </span>
                    <Button
                        variant="outline"
                        disabled={!next}
                        asChild={Boolean(next)}
                    >
                        {next ? (
                            <Link
                                href={next}
                                preserveScroll
                                except={recordOptionProps}
                            >
                                ถัดไป
                            </Link>
                        ) : (
                            'ถัดไป'
                        )}
                    </Button>
                </div>
            )}
        </nav>
    );
}
