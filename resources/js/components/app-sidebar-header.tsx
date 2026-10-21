import { Link, usePage } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { name } = usePage().props;
    const parent = breadcrumbs.at(-2);

    return (
        <>
            <header className="sticky top-0 z-30 flex h-12 shrink-0 items-center border-b bg-background/95 px-2 md:hidden">
                {parent ? (
                    <Link
                        href={parent.href}
                        className="flex h-11 min-w-0 items-center gap-1 rounded-md pr-3 pl-1 text-base active:bg-accent"
                    >
                        <ChevronLeft className="size-5 shrink-0" />
                        <span className="truncate">{parent.title}</span>
                    </Link>
                ) : (
                    <span className="flex items-center gap-2 px-2 text-base font-semibold">
                        <AppLogoIcon className="size-5 fill-current text-primary" />
                        {name}
                    </span>
                )}
            </header>
            <header className="hidden h-14 shrink-0 items-center gap-2 px-4 md:flex">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </header>
        </>
    );
}
