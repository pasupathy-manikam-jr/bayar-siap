import { Link, usePage } from '@inertiajs/react';
import { Bell, BookOpen, Briefcase, Building2, LayoutGrid } from 'lucide-react';
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
import { dashboard, guide, notifications } from '@/routes';
import { edit as editCompany } from '@/routes/company';
import { index as jobsIndex } from '@/routes/jobs';
import type { NavItem } from '@/types';
import { useTranslation } from '@/hooks/use-translation';

export function AppSidebar() {
    const { unreadNotifications } = usePage().props;
    const { t } = useTranslation();

    const navItems: NavItem[] = [
        {
            title: t('Dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: t('Jobs'),
            href: jobsIndex(),
            icon: Briefcase,
        },
        {
            title: t('Notifications'),
            href: notifications(),
            icon: Bell,
            badge: unreadNotifications,
        },
        {
            title: t('Company'),
            href: editCompany(),
            icon: Building2,
        },
        {
            title: t('User guide'),
            href: guide(),
            icon: BookOpen,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="sidebar">
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
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
