import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { createOrganizationBreadcrumb } from '@/lib/breadcrumbs';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Copy,
    CreditCard,
    Key,
    KeyRound,
    Settings,
    ShieldAlert,
    Users,
} from 'lucide-react';
import { type PropsWithChildren, useMemo } from 'react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;

interface SidebarNavItem {
    title: string;
    href: string;
    icon: React.ComponentType<{ className?: string }>;
}

interface SidebarNavGroup {
    label: string;
    items: SidebarNavItem[];
}

function SettingsContent({ children }: PropsWithChildren) {
    const page = usePage<{ organization: OrganizationData; cloud: boolean }>();
    const { organization, cloud } = page.props;
    const currentUrl = page.url;

    const sidebarNavGroups: SidebarNavGroup[] = useMemo(() => {
        if (!organization) return [];

        const base = `/organizations/${organization.slug}/settings`;

        return [
            {
                label: 'Organization',
                items: [
                    {
                        title: 'General',
                        href: `${base}/general`,
                        icon: Settings,
                    },
                    { title: 'Members', href: `${base}/members`, icon: Users },
                ],
            },
            {
                label: 'Registry',
                items: [
                    {
                        title: 'Composer Tokens',
                        href: `${base}/tokens`,
                        icon: Key,
                    },
                    {
                        title: 'SSH Keys',
                        href: `${base}/ssh-keys`,
                        icon: KeyRound,
                    },
                    {
                        title: 'Registry Mirrors',
                        href: `${base}/mirrors`,
                        icon: Copy,
                    },
                ],
            },
            {
                label: 'Security',
                items: [
                    {
                        title: 'Security',
                        href: `${base}/security`,
                        icon: ShieldAlert,
                    },
                ],
            },
            ...(cloud
                ? [
                      {
                          label: 'Billing',
                          items: [
                              {
                                  title: 'Billing',
                                  href: `${base}/billing`,
                                  icon: CreditCard,
                              },
                          ],
                      },
                  ]
                : []),
        ];
    }, [organization, cloud]);

    if (!organization) {
        return null;
    }

    return (
        <div className="mx-auto w-full max-w-7xl min-w-0 space-y-6 p-6">
            <div>
                <h1 className="mb-0.5 text-xl font-medium">
                    Organization Settings
                </h1>
                <p className="text-muted-foreground">
                    Manage {organization.name} settings and preferences
                </p>
            </div>

            <Separator />

            <div className="flex flex-col space-y-8 lg:flex-row lg:space-y-0 lg:space-x-12">
                <aside className="w-full shrink-0 lg:w-48">
                    <nav className="flex flex-col space-y-6">
                        {sidebarNavGroups.map((group) => (
                            <div key={group.label}>
                                <p className="mb-1 px-3 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    {group.label}
                                </p>
                                <div className="flex space-x-2 lg:flex-col lg:space-y-1 lg:space-x-0">
                                    {group.items.map((item) => {
                                        const isActive =
                                            currentUrl === item.href;
                                        return (
                                            <Button
                                                key={item.href}
                                                size="sm"
                                                variant="ghost"
                                                asChild
                                                className={cn(
                                                    'w-full justify-start',
                                                    {
                                                        'bg-muted': isActive,
                                                    },
                                                )}
                                            >
                                                <Link href={item.href}>
                                                    <item.icon className="h-4 w-4" />
                                                    {item.title}
                                                </Link>
                                            </Button>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}
                    </nav>

                    <Separator className="my-4" />

                    <Button
                        size="sm"
                        variant="ghost"
                        asChild
                        className="w-full justify-start"
                    >
                        <Link href="/settings/profile">
                            <ArrowRight className="h-4 w-4" />
                            Personal Settings
                        </Link>
                    </Button>
                </aside>

                <div className="w-full min-w-0 flex-1">{children}</div>
            </div>
        </div>
    );
}

const settingsPageTitles: Record<string, string> = {
    general: 'General',
    members: 'Members',
    tokens: 'Composer Tokens',
    'ssh-keys': 'SSH Keys',
    mirrors: 'Registry Mirrors',
    security: 'Security',
    billing: 'Billing',
};

function OrganizationSettingsLayoutWrapper({ children }: PropsWithChildren) {
    const page = usePage<{
        organization: OrganizationData;
        auth: { organizations: OrganizationData[] };
    }>();
    const { organization, auth } = page.props;
    const currentUrl = page.url;

    const breadcrumbs = useMemo(() => {
        if (!organization) return [];

        const settingsBase = `/organizations/${organization.slug}/settings/general`;
        const pageSlug = currentUrl.split('/').pop() ?? '';
        const pageTitle = settingsPageTitles[pageSlug] ?? 'Settings';

        const items = [
            createOrganizationBreadcrumb(organization, auth.organizations),
            {
                title: 'Settings',
                href: settingsBase,
            },
            {
                title: pageTitle,
                href: currentUrl,
            },
        ];

        return items;
    }, [organization, auth.organizations, currentUrl]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <SettingsContent>{children}</SettingsContent>
        </AppLayout>
    );
}

export default function OrganizationSettingsLayout({
    children,
}: PropsWithChildren) {
    return <SettingsContent>{children}</SettingsContent>;
}

export function withOrganizationSettingsLayout(page: React.ReactNode) {
    return (
        <OrganizationSettingsLayoutWrapper>
            {page}
        </OrganizationSettingsLayoutWrapper>
    );
}
