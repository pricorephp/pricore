import { NavMain } from '@/components/nav-main';
import ReleaseNotesDialog from '@/components/release-notes-dialog';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { useReleaseInfo } from '@/hooks/use-release-info';
import { dashboard } from '@/routes';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    ChartLine,
    GitBranch,
    Package,
    Settings,
    ShieldAlert,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import AppLogoIcon from './app-logo-icon';

export function AppSidebar() {
    const page = usePage<SharedData>();
    const url = page.url;
    const version = page.props.version;
    const organizations = page.props.auth.organizations;
    const { info: releaseInfo } = useReleaseInfo();
    const [releaseNotesOpen, setReleaseNotesOpen] = useState(false);

    // Extract organization slug from URL
    const currentOrgSlug = useMemo(() => {
        const match = url.match(/^\/organizations\/([^/]+)/);

        if (match) {
            return match[1];
        }

        // Fall back to the first organization if available
        return organizations.length > 0 ? organizations[0].slug : null;
    }, [url, organizations]);

    const currentOrg = useMemo(
        () => organizations.find((org) => org.slug === currentOrgSlug),
        [organizations, currentOrgSlug],
    );

    // Build organization-specific navigation
    const orgNavItems: NavItem[] = useMemo(() => {
        if (!currentOrgSlug) return [];

        const items: NavItem[] = [
            {
                title: 'Overview',
                href: `/organizations/${currentOrgSlug}`,
                icon: ChartLine,
            },
            {
                title: 'Repos',
                href: `/organizations/${currentOrgSlug}/repositories`,
                icon: GitBranch,
            },
            {
                title: 'Packages',
                href: `/organizations/${currentOrgSlug}/packages`,
                icon: Package,
            },
            {
                title: 'Security',
                href: `/organizations/${currentOrgSlug}/security`,
                icon: ShieldAlert,
            },
        ];

        if (currentOrg?.permissions?.canViewSettings) {
            items.push({
                title: 'Settings',
                href: `/organizations/${currentOrgSlug}/settings/general`,
                icon: Settings,
            });
        }

        return items;
    }, [currentOrgSlug, currentOrg]);

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
                <button
                    type="button"
                    onClick={() => setReleaseNotesOpen(true)}
                    className="inline-flex items-center justify-center gap-1.5 text-[10.5px] text-sidebar-foreground/50 transition-colors hover:text-sidebar-foreground/70"
                    title={
                        releaseInfo?.isOutdated
                            ? `Update available: v${releaseInfo.latestVersion}`
                            : 'View release notes'
                    }
                >
                    <span>{version ? `v${version}` : 'Releases'}</span>
                    {releaseInfo?.isOutdated && (
                        <span
                            className="size-1.5 rounded-full bg-primary"
                            aria-label="Update available"
                        />
                    )}
                </button>
                <a
                    href="https://docs.pricore.dev"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex flex-col items-center gap-1 rounded-md px-2 py-2.5 text-center text-sidebar-foreground/70 transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                >
                    <BookOpen className="size-5" strokeWidth={1.75} />
                    <span className="text-[10px] leading-tight font-medium">
                        Docs
                    </span>
                </a>
            </SidebarFooter>

            <ReleaseNotesDialog
                open={releaseNotesOpen}
                onOpenChange={setReleaseNotesOpen}
                info={releaseInfo}
            />
        </Sidebar>
    );
}
