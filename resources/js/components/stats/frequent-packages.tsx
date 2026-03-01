import { Skeleton } from '@/components/ui/skeleton';
import { Link } from '@inertiajs/react';
import { Package } from 'lucide-react';

type FrequentPackageData =
    App.Domains.Package.Contracts.Data.FrequentPackageData;

function FrequentPackagesSkeleton() {
    return (
        <div className="space-y-1">
            {Array.from({ length: 5 }).map((_, i) => (
                <div key={i} className="flex items-center px-2 py-2">
                    <div className="flex-1 space-y-1.5">
                        <Skeleton className="h-5 w-3/4" />
                        <Skeleton className="h-4 w-1/3" />
                    </div>
                </div>
            ))}
        </div>
    );
}

interface FrequentPackagesProps {
    organizationSlug: string;
    packages: FrequentPackageData[] | undefined;
}

export function FrequentPackages({
    organizationSlug,
    packages,
}: FrequentPackagesProps) {
    return (
        <div>
            <div className="flex items-center gap-2 px-2 pb-3">
                <Package className="h-4 w-4 text-muted-foreground" />
                <h3 className="text-base font-semibold">Packages</h3>
            </div>
            {packages === undefined ? (
                <FrequentPackagesSkeleton />
            ) : packages.length === 0 ? (
                <p className="px-2 text-sm text-muted-foreground">
                    No packages yet. Packages will appear here once you add
                    repositories and sync them.
                </p>
            ) : (
                <div className="space-y-0.5">
                    {packages.map((pkg) => (
                        <Link
                            key={pkg.uuid}
                            href={`/organizations/${organizationSlug}/packages/${pkg.uuid}`}
                            className="flex items-center rounded-md px-2 py-2 transition-colors hover:bg-accent"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-medium">
                                    {pkg.name}
                                </p>
                                {pkg.latestVersion && (
                                    <p className="text-sm text-muted-foreground">
                                        v{pkg.latestVersion}
                                    </p>
                                )}
                            </div>
                        </Link>
                    ))}
                </div>
            )}
        </div>
    );
}
