import { Link, usePage } from '@inertiajs/react';
import { Bell, LayoutGrid, RefreshCw, UserRound } from 'lucide-react';
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
import { index as alerts } from '@/routes/alerts';
import { show as student } from '@/routes/students';
import { index as sync } from '@/routes/sync';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { unreadAlerts, kids } = usePage().props;

    const mainNavItems: NavItem[] = [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        { title: 'Alerts', href: alerts(), icon: Bell, badge: unreadAlerts },
        { title: 'Sync', href: sync(), icon: RefreshCw },
    ];

    const kidItems: NavItem[] = kids.map((k) => ({ title: k.name, href: student(k.id), icon: UserRound }));

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
                <NavMain items={mainNavItems} label="Overview" />
                {kidItems.length > 0 && <NavMain items={kidItems} label="Kids" />}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
