import { cn } from '@/lib/utils';

/**
 * A row's share of the largest value: the green fill on a full-width pale track, so an empty row still shows its track.
 */
export function ShareBar({
    value,
    max,
    className,
}: {
    value: number;
    max: number;
    className?: string;
}) {
    const percent = max > 0 ? Math.min(100, (value / max) * 100) : 0;

    return (
        <div
            aria-hidden
            className={cn(
                'h-1.5 overflow-hidden rounded-full bg-brand-track',
                className,
            )}
        >
            <div
                className="h-full rounded-full bg-primary"
                style={{ width: `${percent}%` }}
            />
        </div>
    );
}
