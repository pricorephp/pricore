import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Box,
    GitBranch,
    LayoutDashboard,
    Settings,
} from 'lucide-react';
import { useMemo } from 'react';
import AppLogoIcon from './app-logo-icon';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;

export function AppSidebar() {
    const page = usePage<{
        auth: { organizations: OrganizationData[] };
    }>();
    const url = page.url;
    const organizations = page.props.auth.organizations;

    // Extract organization slug from URL, or fall back to first organization
    const currentOrgSlug = useMemo(() => {
        const match = url.match(/^\/organizations\/([^/]+)/);
        if (match) return match[1];
        // Fall back to first organization if available
        return organizations.length > 0 ? organizations[0].slug : null;
    }, [url, organizations]);

    // Build organization-specific navigation
    const orgNavItems: NavItem[] = useMemo(() => {
        if (!currentOrgSlug) return [];

        return [
            {
                title: 'Overview',
                href: `/organizations/${currentOrgSlug}`,
                icon: LayoutDashboard,
            },
            {
                title: 'Packages',
                href: `/organizations/${currentOrgSlug}/packages`,
                icon: Box,
            },
            {
                title: 'Repos',
                href: `/organizations/${currentOrgSlug}/repositories`,
                icon: GitBranch,
            },
            {
                title: 'Settings',
                href: `/organizations/${currentOrgSlug}/settings/general`,
                icon: Settings,
            },
        ];
    }, [currentOrgSlug]);

    return (
        <Sidebar>
            <SidebarHeader className="h-16 items-center justify-center border-b border-sidebar-border">
                <Link
                    href={dashboard()}
                    className="flex items-center justify-center"
                >
                    <AppLogoIcon className="size-7 fill-current text-white dark:text-black" />
                </Link>
            </SidebarHeader>

            <SidebarContent className="pt-2">
                <NavMain items={orgNavItems} />
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border pt-2">
                <a
                    href="https://docs.pricore.dev"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex flex-col items-center gap-1 rounded-md px-2 py-2.5 text-center text-sidebar-foreground/70 transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                >
                    <BookOpen className="size-5" strokeWidth={1.75} />
                    <span className="text-[10px] leading-tight">Docs</span>
                </a>
            </SidebarFooter>
        </Sidebar>
    );
}
