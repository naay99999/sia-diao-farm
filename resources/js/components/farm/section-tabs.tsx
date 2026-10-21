import { cn } from '@/lib/utils';

export type SectionTab<T extends string> = {
    value: T;
    label: string;
    count?: number;
};

/**
 * Underline tabs on a full-width rule; the row scrolls sideways on a phone instead of wrapping.
 */
export function SectionTabs<T extends string>({
    tabs,
    value,
    onChange,
    label,
}: {
    tabs: SectionTab<T>[];
    value: T;
    onChange: (value: T) => void;
    label: string;
}) {
    return (
        <div
            role="tablist"
            aria-label={label}
            className="-mx-4 flex [scrollbar-width:none] overflow-x-auto border-b px-4 md:mx-0 md:px-0"
        >
            {tabs.map((tab) => {
                const isSelected = tab.value === value;

                return (
                    <button
                        key={tab.value}
                        type="button"
                        role="tab"
                        id={`tab-${tab.value}`}
                        aria-selected={isSelected}
                        aria-controls={`panel-${tab.value}`}
                        onClick={() => onChange(tab.value)}
                        className={cn(
                            '-mb-px flex h-12 shrink-0 items-center gap-1.5 border-b-2 border-transparent px-3 text-base whitespace-nowrap text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none md:h-11 md:text-sm',
                            isSelected &&
                                'border-foreground font-medium text-foreground',
                        )}
                    >
                        {tab.label}
                        {tab.count !== undefined && (
                            <span className="text-sm text-muted-foreground">
                                {tab.count.toLocaleString('th-TH')}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}
