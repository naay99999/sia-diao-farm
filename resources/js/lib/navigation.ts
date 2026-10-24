import {
    LayoutDashboard,
    NotebookText,
    Settings,
    Sparkles,
    Trees,
    UserRound,
} from 'lucide-react';
import { overview } from '@/routes';
import { index as assistantIndex } from '@/routes/assistant';
import { index as orchardsIndex } from '@/routes/orchards';
import { edit as profileEdit } from '@/routes/profile';
import { index as recordsIndex } from '@/routes/records';
import type { NavItem } from '@/types';

/**
 * The app's destinations, shared by the desktop sidebar and the phone's bottom bar.
 */
export const mainNavItems: NavItem[] = [
    {
        title: 'ภาพรวม',
        href: overview(),
        icon: LayoutDashboard,
        matches: ['/overview'],
    },
    {
        title: 'สวน',
        href: orchardsIndex(),
        icon: Trees,
        matches: ['/orchards', '/trees', '/seasons', '/t/'],
    },
    {
        title: 'บันทึก',
        href: recordsIndex('care'),
        icon: NotebookText,
        matches: ['/records', '/expenses'],
        placement: 'desktop',
    },
    {
        title: 'ผู้ช่วย AI',
        href: assistantIndex(),
        icon: Sparkles,
        matches: ['/assistant'],
    },
    {
        title: 'ตั้งค่า',
        href: profileEdit(),
        icon: Settings,
        matches: ['/settings'],
        placement: 'desktop',
    },
];


export const accountNavItem: NavItem = {
    title: 'บัญชี',
    href: profileEdit(),
    icon: UserRound,
    matches: ['/settings'],
};

/**
 * The phone's bottom bar: items made for everywhere, plus the account entry.
 */
export const bottomNavItems: NavItem[] = [
    ...mainNavItems.filter((item) => item.placement !== 'desktop'),
    accountNavItem,
];

export function isNavItemActive(item: NavItem, currentPath: string): boolean {
    return (item.matches ?? []).some((prefix) =>
        currentPath.startsWith(prefix),
    );
}
