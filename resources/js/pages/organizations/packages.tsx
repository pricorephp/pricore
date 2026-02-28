import { show } from '@/actions/App/Domains/Package/Http/Controllers/PackageController';
import { EmptyState } from '@/components/empty-state';
import HeadingSmall from '@/components/heading-small';
import PackageCard from '@/components/package-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { createOrganizationBreadcrumb } from '@/lib/breadcrumbs';
import { Head, Link, usePage } from '@inertiajs/react';
import { GitBranch, Package } from 'lucide-react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;
type PackageData = App.Domains.Package.Contracts.Data.PackageData;

interface PackagesPageProps {
    organization: OrganizationData;
    packages: PackageData[];
}

export default function Packages({
    organization,
    packages,
}: PackagesPageProps) {
    const { auth } = usePage<{
        auth: { organizations: OrganizationData[] };
    }>().props;

    const breadcrumbs = [
        createOrganizationBreadcrumb(organization, auth.organizations),
        {
            title: 'Packages',
            href: `/organizations/${organization.slug}/packages`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Packages - ${organization.name}`} />

            <div className="mx-auto w-7xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <HeadingSmall
                        title="Packages"
                        description="Composer packages in this organization"
                    />
                    <Button asChild>
                        <Link
                            href={`/organizations/${organization.slug}/repositories`}
                        >
                            <GitBranch className="h-4 w-4" />
                            Manage Repositories
                        </Link>
                    </Button>
                </div>

                {packages.length === 0 ? (
                    <EmptyState
                        icon={Package}
                        title="No packages yet"
                        description="Connect a Git repository to automatically discover and sync Composer packages."
                        action={{
                            label: 'Connect Your First Repository',
                            href: `/organizations/${organization.slug}/repositories`,
                        }}
                    />
                ) : (
                    <div className="divide-y divide-border rounded-lg border bg-card">
                        {packages.map((pkg) => (
                            <Link
                                key={pkg.uuid}
                                href={show.url([organization.slug, pkg.uuid])}
                                className="group flex items-center justify-between px-4 py-3 transition-colors hover:bg-accent/50"
                            >
                                <PackageCard package={pkg} />
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
