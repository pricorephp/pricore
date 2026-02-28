import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import {
    GitBranch,
    Key,
    LayoutGrid,
    Lock,
    Package,
    Paintbrush,
    ShieldCheck,
    User,
    Users,
} from 'lucide-react';
import { useCallback } from 'react';

const staticPages = [
    { name: 'Dashboard', href: '/', icon: LayoutGrid },
    { name: 'Profile', href: '/settings/profile', icon: User },
    { name: 'Password', href: '/settings/password', icon: Lock },
    { name: 'Appearance', href: '/settings/appearance', icon: Paintbrush },
    {
        name: 'Two-Factor Auth',
        href: '/settings/two-factor',
        icon: ShieldCheck,
    },
    {
        name: 'Git Providers',
        href: '/settings/git-credentials',
        icon: GitBranch,
    },
    { name: 'Tokens', href: '/settings/tokens', icon: Key },
    { name: 'Organizations', href: '/settings/organizations', icon: Users },
];

export function CommandPalette({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const page = usePage<SharedData>();
    const organizations = page.props.auth.organizations;
    const packages = page.props.search?.packages ?? [];
    const repositories = page.props.search?.repositories ?? [];

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
            </CommandList>
        </CommandDialog>
    );
}
