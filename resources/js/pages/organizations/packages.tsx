import HeadingSmall from '@/components/heading-small';
import PackageCard from '@/components/package-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { show } from '@/actions/App/Domains/Package/Http/Controllers/PackageController';
import { Link } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import { GitBranch } from 'lucide-react';

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

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: organization.name,
            href: `/organizations/${organization.slug}`,
        },
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
                        <Link href={`/organizations/${organization.slug}/repositories`}>
                            <GitBranch className="mr-2 h-4 w-4" />
                            Manage Repositories
                        </Link>
                    </Button>
                </div>

                {packages.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-12 text-center">
                        <p className="text-sm text-muted-foreground">
                            No packages yet. Connect a Git repository to
                            automatically discover and sync packages.
                        </p>
                        <Button
                            className="mt-4"
                            variant="outline"
                            asChild
                        >
                            <Link href={`/organizations/${organization.slug}/repositories`}>
                                <GitBranch className="mr-2 h-4 w-4" />
                                Connect Your First Repository
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {packages.map((pkg) => (
                            <Link
                                key={pkg.uuid}
                                href={show.url([organization.slug, pkg.uuid])}
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
