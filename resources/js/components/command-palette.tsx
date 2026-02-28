import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { router, usePage } from '@inertiajs/react';
import {
    GitBranch,
    KeyRound,
    LayoutDashboard,
    Package,
    Palette,
    ShieldCheck,
    User,
    Users,
    Wrench,
} from 'lucide-react';
import { useCallback } from 'react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;

interface SearchPackageData {
    uuid: string;
    name: string;
    description: string | null;
    organizationName: string;
    organizationSlug: string;
}

interface SearchRepositoryData {
    uuid: string;
    name: string;
    provider: string;
    providerLabel: string;
    organizationName: string;
    organizationSlug: string;
}

const staticPages = [
    { name: 'Dashboard', href: '/', icon: LayoutDashboard },
    { name: 'Profile', href: '/settings/profile', icon: User },
    { name: 'Password', href: '/settings/password', icon: KeyRound },
    { name: 'Appearance', href: '/settings/appearance', icon: Palette },
    {
        name: 'Two-Factor Authentication',
        href: '/settings/two-factor',
        icon: ShieldCheck,
    },
    {
        name: 'Git Credentials',
        href: '/settings/git-credentials',
        icon: Wrench,
    },
    { name: 'Tokens', href: '/settings/tokens', icon: KeyRound },
    { name: 'Organizations', href: '/settings/organizations', icon: Users },
];

export function CommandPalette({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const page = usePage<{
        auth: { organizations: OrganizationData[] };
        search: {
            packages: SearchPackageData[];
            repositories: SearchRepositoryData[];
        };
    }>();
    const organizations = page.props.auth.organizations;
    const { packages, repositories } = page.props.search;

    const navigate = useCallback(
        (href: string) => {
            onOpenChange(false);
            router.visit(href);
        },
        [onOpenChange],
    );

    return (
        <CommandDialog open={open} onOpenChange={onOpenChange}>
            <CommandInput placeholder="Search pages, organizations, packages..." />
            <CommandList>
                <CommandEmpty>No results found.</CommandEmpty>

                <CommandGroup heading="Pages">
                    {staticPages.map((page) => (
                        <CommandItem
                            key={page.href}
                            onSelect={() => navigate(page.href)}
                        >
                            <page.icon className="size-4 opacity-60" />
                            <span>{page.name}</span>
                        </CommandItem>
                    ))}
                </CommandGroup>

                {organizations.length > 0 && (
                    <CommandGroup heading="Organizations">
                        {organizations.map((org) => (
                            <CommandItem
                                key={org.uuid}
                                onSelect={() =>
                                    navigate(`/organizations/${org.slug}`)
                                }
                            >
                                <Users className="size-4 opacity-60" />
                                <span>{org.name}</span>
                            </CommandItem>
                        ))}
                    </CommandGroup>
                )}

                {packages.length > 0 && (
                    <CommandGroup heading="Packages">
                        {packages.map((pkg) => (
                            <CommandItem
                                key={pkg.uuid}
                                onSelect={() =>
                                    navigate(
                                        `/organizations/${pkg.organizationSlug}/packages/${pkg.uuid}`,
                                    )
                                }
                            >
                                <Package className="size-4 opacity-60" />
                                <span>{pkg.name}</span>
                                <span className="ml-auto text-xs text-muted-foreground">
                                    {pkg.organizationName}
                                </span>
                            </CommandItem>
                        ))}
                    </CommandGroup>
                )}

                {repositories.length > 0 && (
                    <CommandGroup heading="Repositories">
                        {repositories.map((repo) => (
                            <CommandItem
                                key={repo.uuid}
                                onSelect={() =>
                                    navigate(
                                        `/organizations/${repo.organizationSlug}/repositories/${repo.uuid}`,
                                    )
                                }
                            >
                                <GitBranch className="size-4 opacity-60" />
                                <span>{repo.name}</span>
                                <span className="ml-auto text-xs text-muted-foreground">
                                    {repo.providerLabel} &middot;{' '}
                                    {repo.organizationName}
                                </span>
                            </CommandItem>
                        ))}
                    </CommandGroup>
                )}
            </CommandList>
        </CommandDialog>
    );
}
