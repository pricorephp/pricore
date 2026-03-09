import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { CommandPalette } from '@/components/command-palette';
import { CommandPaletteContext } from '@/hooks/use-command-palette';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren, useEffect, useMemo, useState } from 'react';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    const page = usePage<SharedData>();
    const organizations = page.props.auth.organizations;

    const currentOrg = useMemo(() => {
        const match = page.url.match(/^\/organizations\/([^/]+)/);
        if (match) {
            return organizations.find((org) => org.slug === match[1]);
        }
        return undefined;
    }, [page.url, organizations]);

    const showTrialExpiredBanner = currentOrg?.trialExpired === true;

    const [commandPaletteOpen, setCommandPaletteOpen] = useState(false);

    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                setCommandPaletteOpen((prev) => !prev);
            }
        };

        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, []);

    return (
        <CommandPaletteContext.Provider
            value={{ open: commandPaletteOpen, setOpen: setCommandPaletteOpen }}
        >
            <AppShell variant="sidebar">
                <AppSidebar />
                <AppContent variant="sidebar">
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                    {showTrialExpiredBanner && currentOrg && (
                        <div className="border-b bg-destructive/10 px-4 py-2.5 text-center text-sm text-destructive">
                            Your trial has expired.{' '}
                            <Link
                                href={`/organizations/${currentOrg.slug}/settings/billing`}
                                className="font-medium underline underline-offset-4 hover:no-underline"
                            >
                                Subscribe to continue using Pricore
                            </Link>
                        </div>
                    )}
                    {children}
                </AppContent>
            </AppShell>
            <CommandPalette
                open={commandPaletteOpen}
                onOpenChange={setCommandPaletteOpen}
            />
        </CommandPaletteContext.Provider>
    );
}
