import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import OrganizationSwitcher from '@/components/organization-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Box, GitBranch, LayoutDashboard, Settings } from 'lucide-react';
import { useMemo } from 'react';
import AppLogoIcon from './app-logo-icon';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;

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
                title: 'Repos',
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

    return (
        <Sidebar>
            <SidebarHeader className="items-center border-b border-sidebar-border pb-3">
                <Link
                    href={dashboard()}
                    className="flex items-center justify-center"
                >
                    <AppLogoIcon className="size-7 fill-current text-white dark:text-black" />
                </Link>

                {auth.organizations.length > 0 && (
                    <OrganizationSwitcher
                        organizations={auth.organizations}
                        currentOrganization={currentOrganization || undefined}
                    />
                )}
            </SidebarHeader>

            <SidebarContent className="pt-2">
                <NavMain items={orgNavItems} />
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border pt-2">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
