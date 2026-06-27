import { Link } from '@inertiajs/react';
import { BookOpen, FolderGit2, Leaf, LayoutGrid, Sprout, Trees } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as plotsIndex } from '@/routes/plots';
import { index as fruitTypesIndex } from '@/routes/fruit-types';
import { index as fruitVarietiesIndex } from '@/routes/fruit-varieties';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'แดชบอร์ด',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'แปลงผลไม้',
        href: plotsIndex(),
        icon: Trees,
    },
    {
        title: 'ชนิดผลไม้',
        href: fruitTypesIndex(),
        icon: Sprout,
    },
    {
        title: 'พันธุ์ผลไม้',
        href: fruitVarietiesIndex(),
        icon: Leaf,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'ที่เก็บโค้ด',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'เอกสารประกอบ',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
