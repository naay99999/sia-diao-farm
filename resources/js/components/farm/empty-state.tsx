import type { ReactNode } from 'react';

/**
 * An empty block keeps its frame: one sentence in a dashed box, with the action that fills it.
 */
export function EmptyState({
    children,
    action,
}: {
    children: ReactNode;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-start gap-3 rounded-lg border border-dashed border-input px-4 py-5 text-base text-muted-foreground sm:flex-row sm:items-center sm:justify-between md:text-sm">
            <p>{children}</p>
            {action}
        </div>
    );
}
