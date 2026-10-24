import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /** Path prefixes that also mark this item as the current section. */
    matches?: string[];
    /** Where the item is shown: 'desktop' keeps it out of the phone's bottom bar. */
    placement?: 'everywhere' | 'desktop';
};
