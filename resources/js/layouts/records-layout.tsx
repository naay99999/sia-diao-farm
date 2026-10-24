import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { PageContainer } from '@/components/page-container';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { index as expensesIndex } from '@/routes/expenses';
import { index as recordsIndex, trash } from '@/routes/records';
import type { RecordFilterValues } from '@/types/farm';

const tabs = [
    {
        title: 'การดูแล',
        href: (query: string) => recordsIndex.url('care') + query,
    },
    {
        title: 'สภาพต้น/ปัญหา',
        href: (query: string) => recordsIndex.url('observations') + query,
    },
    {
        title: 'ผลผลิต',
        href: (query: string) => recordsIndex.url('harvests') + query,
    },
    {
        title: 'การขาย',
        href: (query: string) => recordsIndex.url('sales') + query,
    },
    {
        title: 'ค่าใช้จ่าย',
        href: (query: string) => expensesIndex.url() + query,
    },
    { title: 'ถังขยะ', href: () => trash.url() },
];

/**
 * Switching tabs keeps the filter bar's choices, since every type shares them.
 */
function filterQuery(filters: Partial<RecordFilterValues> | undefined): string {
    const params = new URLSearchParams();

    Object.entries(filters ?? {}).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            params.set(key, String(value));
        }
    });

    const query = params.toString();

    return query === '' ? '' : `?${query}`;
}

export default function RecordsLayout({ children }: PropsWithChildren) {
    const { currentUrl } = useCurrentUrl();
    const { filters } = usePage<{ filters?: Partial<RecordFilterValues> }>()
        .props;
    const query = filterQuery(filters);

    return (
        <PageContainer>
            <Heading title="บันทึก" description="ทุกอย่างที่จดไว้ในฟาร์ม" />

            <nav
                aria-label="ประเภทบันทึก"
                className="-mx-4 flex [scrollbar-width:none] overflow-x-auto border-b px-4 md:mx-0 md:px-0"
            >
                {tabs.map((tab) => {
                    const href = tab.href(query);
                    const isActive = currentUrl === href.split('?')[0];

                    return (
                        <Link
                            key={tab.title}
                            href={href}
                            aria-current={isActive ? 'page' : undefined}
                            className={cn(
                                '-mb-px flex h-12 shrink-0 items-center border-b-2 border-transparent px-2.5 text-lg font-medium whitespace-nowrap text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none md:h-10 md:px-3 md:text-base',
                                isActive && 'border-primary text-foreground',
                            )}
                        >
                            {tab.title}
                        </Link>
                    );
                })}
            </nav>

            {children}
        </PageContainer>
    );
}
