import { Link } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import {
    accountNavItem,
    isNavItemActive,
    mainNavItems,
} from '@/lib/navigation';
import { cn } from '@/lib/utils';

/**
 * The phone's navigation: every destination one thumb-tap away, below the md breakpoint only.
 */
export function AppBottomNav() {
    const { currentUrl } = useCurrentUrl();

    return (
        <nav
            aria-label="เมนูหลัก"
            className="fixed inset-x-0 bottom-0 z-40 border-t bg-card pb-[env(safe-area-inset-bottom)] md:hidden"
        >
            <ul className="grid grid-cols-3">
                {[...mainNavItems, accountNavItem].map((item) => {
                    const isActive = isNavItemActive(item, currentUrl);

                    return (
                        <li key={item.title}>
                            <Link
                                href={item.href}
                                prefetch
                                aria-current={isActive ? 'page' : undefined}
                                className={cn(
                                    'flex h-16 flex-col items-center justify-center gap-1 text-sm text-muted-foreground transition-colors active:bg-accent',
                                    isActive && 'font-medium text-foreground',
                                )}
                            >
                                {item.icon && (
                                    <item.icon
                                        className={cn(
                                            'size-6',
                                            isActive && 'text-primary',
                                        )}
                                    />
                                )}
                                {item.title}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
