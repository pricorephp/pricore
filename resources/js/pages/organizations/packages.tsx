import { show } from '@/actions/App/Domains/Package/Http/Controllers/PackageController';
import { EmptyState } from '@/components/empty-state';
import HeadingSmall from '@/components/heading-small';
import PackageCard, { splitPackageName } from '@/components/package-card';
import { Button } from '@/components/ui/button';
import { CardList } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { createOrganizationBreadcrumb } from '@/lib/breadcrumbs';
import { Head, Link, usePage } from '@inertiajs/react';
import { GitBranch, Package, Search } from 'lucide-react';
import { useMemo, useState } from 'react';

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

    const [query, setQuery] = useState('');

    const vendors = useMemo(() => {
        const needle = query.trim().toLowerCase();
        const groups = new Map<string, PackageData[]>();

        packages
            .filter(
                (pkg) =>
                    needle === '' ||
                    pkg.name.toLowerCase().includes(needle) ||
                    pkg.description?.toLowerCase().includes(needle),
            )
            .forEach((pkg) => {
                const vendor = splitPackageName(pkg.name)[0] ?? '';
                groups.set(vendor, [...(groups.get(vendor) ?? []), pkg]);
            });

        return [...groups.entries()].sort(([a], [b]) => a.localeCompare(b));
    }, [packages, query]);

    const vendorCount = new Set(
        packages.map((pkg) => splitPackageName(pkg.name)[0] ?? ''),
    ).size;

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
                        description={
                            packages.length > 0
                                ? `${packages.length} ${packages.length === 1 ? 'package' : 'packages'} across ${vendorCount} ${vendorCount === 1 ? 'vendor' : 'vendors'}`
                                : 'Composer packages in this organization'
                        }
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
                    <>
                        <div className="relative max-w-sm">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder="Filter packages..."
                                className="pl-9"
                            />
                        </div>

                        {vendors.length === 0 ? (
                            <p className="py-8 text-center text-muted-foreground">
                                No packages match &ldquo;{query}&rdquo;
                            </p>
                        ) : (
                            <div className="space-y-6">
                                {vendors.map(([vendor, vendorPackages]) => (
                                    <section key={vendor} className="space-y-2">
                                        <VendorHeader
                                            vendor={vendor}
                                            count={vendorPackages.length}
                                        />
                                        <CardList>
                                            {vendorPackages.map((pkg) => (
                                                <Link
                                                    key={pkg.uuid}
                                                    href={show.url([
                                                        organization.slug,
                                                        pkg.uuid,
                                                    ])}
                                                    className="group flex items-center gap-6 px-4 py-3 transition-colors hover:bg-accent/50"
                                                >
                                                    <PackageCard
                                                        package={pkg}
                                                        hideVendor
                                                    />
                                                </Link>
                                            ))}
                                        </CardList>
                                    </section>
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}

function VendorHeader({ vendor, count }: { vendor: string; count: number }) {
    return (
        <div className="flex items-center gap-2.5 px-1">
            <span className="flex size-6 items-center justify-center rounded-md border bg-card font-mono text-xs font-semibold text-muted-foreground uppercase">
                {(vendor || '?').charAt(0)}
            </span>
            <span className="font-mono font-medium">{vendor || 'Other'}</span>
            <span className="text-muted-foreground">
                {count} {count === 1 ? 'package' : 'packages'}
            </span>
        </div>
    );
}
