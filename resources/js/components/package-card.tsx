import { ArrowUpRight, Package } from 'lucide-react';
import { DateTime } from 'luxon';

type PackageData = App.Domains.Package.Contracts.Data.PackageData;

interface PackageCardProps {
    package: PackageData;
    hideRepository?: boolean;
}

export default function PackageCard({ package: pkg }: PackageCardProps) {
    return (
        <>
            <div className="flex items-center gap-2">
                <Package className="size-4 text-muted-foreground" />
                <span className="font-medium transition-colors group-hover:text-primary">
                    {pkg.name}
                </span>
            </div>
            <div className="flex items-center gap-4 text-sm text-muted-foreground">
                {pkg.latestVersion && (
                    <span className="font-mono">{pkg.latestVersion}</span>
                )}
                {pkg.versionsCount > 0 && (
                    <span>
                        {pkg.versionsCount}{' '}
                        {pkg.versionsCount === 1 ? 'version' : 'versions'}
                    </span>
                )}
                <span>
                    Updated {DateTime.fromISO(pkg.updatedAt).toRelative()}
                </span>
                <ArrowUpRight className="h-4 w-4 text-muted-foreground/50 transition-all group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-muted-foreground" />
            </div>
        </>
    );
}
