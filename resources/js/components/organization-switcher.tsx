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
            <SelectTrigger className="h-8 w-full border-0 border-b-0 bg-white dark:bg-neutral-950">
                <SelectValue placeholder="Select organization" />
            </SelectTrigger>
            <SelectContent className="bg-white dark:bg-neutral-950">
                {organizations.map((org) => (
                    <SelectItem key={org.uuid} value={org.slug}>
                        {org.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
