import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import OrganizationSwitcher from '@/components/organization-switcher';
import { Separator } from '@/components/ui/separator';
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
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Box, GitBranch, LayoutDashboard, Settings } from 'lucide-react';
import { useMemo } from 'react';
import AppLogo from './app-logo';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;

const mainNavItems: NavItem[] = [];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const page = usePage<{
        auth: { organizations: OrganizationData[] };
    }>();
    const { auth } = page.props;
    const url = page.url;

    // Detect if we're viewing an organization
    const currentOrganization = useMemo(() => {
        const match = url.match(/^\/organizations\/([^/]+)/);
        if (match) {
            const slug = match[1];
            return auth.organizations.find((org) => org.slug === slug) || null;
        }

        // No organization selected (e.g., on dashboard)
        return null;
    }, [url, auth.organizations]);

    // Build organization-specific navigation
    const orgNavItems: NavItem[] = useMemo(() => {
        if (!currentOrganization) return [];

        return [
            {
                title: 'Overview',
                href: `/organizations/${currentOrganization.slug}`,
                icon: LayoutDashboard,
            },
            {
                title: 'Packages',
                href: `/organizations/${currentOrganization.slug}/packages`,
                icon: Box,
            },
            {
                title: 'Repositories',
                href: `/organizations/${currentOrganization.slug}/repositories`,
                icon: GitBranch,
            },
            {
                title: 'Settings',
                href: `/organizations/${currentOrganization.slug}/settings/general`,
                icon: Settings,
            },
        ];
    }, [currentOrganization]);

    const navigationItems = currentOrganization
        ? [...mainNavItems, ...orgNavItems]
        : mainNavItems;

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

                {auth.organizations.length > 0 && (
                    <>
                        <Separator />
                        <OrganizationSwitcher
                            organizations={auth.organizations}
                            currentOrganization={
                                currentOrganization || undefined
                            }
                        />
                    </>
                )}
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navigationItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
