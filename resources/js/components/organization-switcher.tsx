import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useSidebar } from '@/components/ui/sidebar';
import { router } from '@inertiajs/react';

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
    const { state } = useSidebar();
    const isCollapsed = state === 'collapsed';

    if (organizations.length === 0) {
        return null;
    }

    if (isCollapsed) {
        return null;
    }

    const handleOrganizationChange = (slug: string) => {
        router.visit(`/organizations/${slug}`);
    };

    return (
        <Select
            value={currentOrganization?.slug}
            onValueChange={handleOrganizationChange}
        >
            <SelectTrigger className="h-8 w-full rounded-sm border border-sidebar-border bg-sidebar-accent/50 text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground">
                <SelectValue placeholder="Select organization" />
            </SelectTrigger>
            <SelectContent className="border-sidebar-border bg-sidebar text-sidebar-foreground">
                {organizations.map((org) => (
                    <SelectItem
                        key={org.uuid}
                        value={org.slug}
                        className="text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus:bg-sidebar-accent focus:text-sidebar-accent-foreground"
                    >
                        {org.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
