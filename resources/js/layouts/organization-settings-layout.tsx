import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';
import { GitBranch, Key, Settings, Users } from 'lucide-react';
import { type PropsWithChildren, useMemo } from 'react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;

interface SidebarNavItem {
    title: string;
    href: string;
    icon: React.ComponentType<{ className?: string }>;
}

export default function OrganizationSettingsLayout({
    children,
}: PropsWithChildren) {
    const page = usePage<{ organization: OrganizationData }>();
    const { organization } = page.props;
    const currentUrl = page.url;

    const sidebarNavItems: SidebarNavItem[] = useMemo(() => {
        if (!organization) return [];

        return [
            {
                title: 'General',
                href: `/organizations/${organization.slug}/settings/general`,
                icon: Settings,
            },
            {
                title: 'Members',
                href: `/organizations/${organization.slug}/settings/members`,
                icon: Users,
            },
            {
                title: 'API Tokens',
                href: `/organizations/${organization.slug}/settings/tokens`,
                icon: Key,
            },
            {
                title: 'Git Providers',
                href: `/organizations/${organization.slug}/settings/git-credentials`,
                icon: GitBranch,
            },
        ];
    }, [organization]);

    if (!organization) {
        return null;
    }

    return (
        <div className="mx-auto w-full max-w-7xl min-w-0 space-y-6 p-6">
            <div>
                <h1 className="text-3xl font-bold tracking-tight">
                    Organization Settings
                </h1>
                <p className="text-muted-foreground">
                    Manage {organization.name} settings and preferences
                </p>
            </div>

            <Separator />

            <div className="flex flex-col space-y-8 lg:flex-row lg:space-y-0 lg:space-x-12">
                <aside className="w-full shrink-0 lg:w-48">
                    <nav className="flex space-x-2 lg:flex-col lg:space-y-1 lg:space-x-0">
                        {sidebarNavItems.map((item) => {
                            const isActive = currentUrl === item.href;
                            return (
                                <Button
                                    key={item.href}
                                    size="sm"
                                    variant="ghost"
                                    asChild
                                    className={cn('w-full justify-start', {
                                        'bg-muted': isActive,
                                    })}
                                >
                                    <Link href={item.href}>
                                        <item.icon className="h-4 w-4" />
                                        {item.title}
                                    </Link>
                                </Button>
                            );
                        })}
                    </nav>
                </aside>

                <div className="w-full min-w-0 flex-1">{children}</div>
            </div>
        </div>
    );
}
