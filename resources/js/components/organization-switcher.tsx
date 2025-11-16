import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useSidebar } from '@/components/ui/sidebar';
import { router } from '@inertiajs/react';
import { Building2 } from 'lucide-react';

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
        <div className="flex items-center gap-2 px-2 py-2">
            <Building2 className="h-4 w-4 shrink-0 text-muted-foreground" />
            <Select
                value={currentOrganization?.slug}
                onValueChange={handleOrganizationChange}
            >
                <SelectTrigger className="h-8 w-full bg-white dark:bg-neutral-950">
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
        </div>
    );
}
