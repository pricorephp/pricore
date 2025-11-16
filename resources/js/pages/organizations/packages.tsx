import HeadingSmall from '@/components/heading-small';
import PackageCard from '@/components/package-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Plus } from 'lucide-react';

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
                    <Button>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Package
                    </Button>
                </div>

                {packages.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-12 text-center">
                        <p className="text-sm text-muted-foreground">
                            No packages yet. Add a repository or create a
                            package to get started.
                        </p>
                        <Button className="mt-4" variant="outline">
                            <Plus className="mr-2 h-4 w-4" />
                            Add Your First Package
                        </Button>
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {packages.map((pkg) => (
                            <PackageCard key={pkg.uuid} package={pkg} />
                        ))}
                    </div>
                )}

                <div className="rounded-md border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950">
                    <p className="text-sm font-medium text-blue-900 dark:text-blue-100">
                        About Packages
                    </p>
                    <p className="mt-1 text-sm text-blue-700 dark:text-blue-300">
                        Packages are Composer libraries hosted in your private
                        registry. You can add packages by connecting Git
                        repositories or creating them manually. Each package can
                        have multiple versions and is accessible via Composer
                        using API tokens.
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
