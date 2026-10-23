import { Sparkles, Trees, UserRound, Wallet } from 'lucide-react';
import { index as assistantIndex } from '@/routes/assistant';
import { index as expensesIndex } from '@/routes/expenses';
import { index as orchardsIndex } from '@/routes/orchards';
import { edit as profileEdit } from '@/routes/profile';
import type { NavItem } from '@/types';

/**
 * The app's destinations, shared by the desktop sidebar and the phone's bottom bar.
 */
export const mainNavItems: NavItem[] = [
    {
        title: 'สวน',
        href: orchardsIndex(),
        icon: Trees,
        matches: ['/orchards', '/trees', '/seasons', '/t/'],
    },
    {
        title: 'ค่าใช้จ่าย',
        href: expensesIndex(),
        icon: Wallet,
        matches: ['/expenses'],
    },
    {
        title: 'ผู้ช่วย AI',
        href: assistantIndex(),
        icon: Sparkles,
        matches: ['/assistant'],
    },
];

export const accountNavItem: NavItem = {
    title: 'บัญชี',
    href: profileEdit(),
    icon: UserRound,
    matches: ['/settings'],
};

export function isNavItemActive(item: NavItem, currentPath: string): boolean {
    return (item.matches ?? []).some((prefix) =>
        currentPath.startsWith(prefix),
    );
}
