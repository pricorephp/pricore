import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { ChevronsUpDown } from 'lucide-react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;

interface OrganizationSwitcherProps {
    organizations: OrganizationData[];
    currentOrganization?: OrganizationData;
}

export default function OrganizationSwitcher({
    organizations,
    currentOrganization,
}: OrganizationSwitcherProps) {
    if (organizations.length === 0) {
        return null;
    }

    const handleOrganizationChange = (slug: string) => {
        router.visit(`/organizations/${slug}`);
    };

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map((word) => word[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    className={cn(
                        'flex w-full items-center justify-center gap-1 rounded-md px-2 py-1.5',
                        'text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                        'focus:outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring',
                    )}
                >
                    <div className="flex size-7 items-center justify-center rounded-md bg-sidebar-accent text-xs font-semibold">
                        {currentOrganization
                            ? getInitials(currentOrganization.name)
                            : '?'}
                    </div>
                    <ChevronsUpDown className="size-3.5 opacity-50" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                side="right"
                className="min-w-48"
            >
                {organizations.map((org) => (
                    <DropdownMenuItem
                        key={org.uuid}
                        onClick={() => handleOrganizationChange(org.slug)}
                        className={cn(
                            currentOrganization?.uuid === org.uuid &&
                                'bg-accent',
                        )}
                    >
                        <div className="mr-2 flex size-6 items-center justify-center rounded bg-muted text-xs font-medium">
                            {getInitials(org.name)}
                        </div>
                        {org.name}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
