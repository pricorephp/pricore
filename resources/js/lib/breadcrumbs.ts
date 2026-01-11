import { type BreadcrumbItem } from '@/types';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;

export function createOrganizationBreadcrumb(
    currentOrg: OrganizationData,
    allOrgs: OrganizationData[],
): BreadcrumbItem {
    return {
        title: currentOrg.name,
        href: `/organizations/${currentOrg.slug}`,
        dropdown: {
            items: allOrgs.map((org) => ({
                id: org.uuid,
                title: org.name,
                href: `/organizations/${org.slug}`,
                active: org.uuid === currentOrg.uuid,
            })),
        },
    };
}
