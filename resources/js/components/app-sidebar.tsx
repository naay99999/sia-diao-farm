import { Link } from '@inertiajs/react';
import { Award, ClipboardList, Leaf, LayoutGrid, Sprout, Tags, Trees, Wallet } from 'lucide-react';
import AppLogo from '@/components/app-logo';
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
import { index as activityTypesIndex } from '@/routes/activity-types';
import { index as expenseCategoriesIndex } from '@/routes/expense-categories';
import { index as expensesIndex } from '@/routes/expenses';
import { index as fruitTypesIndex } from '@/routes/fruit-types';
import { index as fruitVarietiesIndex } from '@/routes/fruit-varieties';
import { index as gradesIndex } from '@/routes/grades';
import { index as plotsIndex } from '@/routes/plots';
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
    {
        title: 'เกรดผลไม้',
        href: gradesIndex(),
        icon: Award,
    },
    {
        title: 'ค่าใช้จ่ายส่วนกลาง',
        href: expensesIndex(),
        icon: Wallet,
    },
    {
        title: 'ประเภทกิจกรรม',
        href: activityTypesIndex(),
        icon: ClipboardList,
    },
    {
        title: 'หมวดค่าใช้จ่าย',
        href: expenseCategoriesIndex(),
        icon: Tags,
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
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
