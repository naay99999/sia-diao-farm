import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * The padded column every app page sits in, so gutters and spacing match from page to page.
 */
export function PageContainer({
    className,
    children,
}: {
    className?: string;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'flex flex-1 flex-col gap-6 px-4 py-5 md:px-6 md:py-6',
                className,
            )}
        >
            {children}
        </div>
    );
}
