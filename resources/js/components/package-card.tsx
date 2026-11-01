import { ArrowUpRight, Copy, GitBranch, Globe, Tag } from 'lucide-react';
import { DateTime } from 'luxon';

type PackageData = App.Domains.Package.Contracts.Data.PackageData;

interface PackageCardProps {
    package: PackageData;
    hideRepository?: boolean;
    hideVendor?: boolean;
}

export default function PackageCard({
    package: pkg,
    hideRepository = false,
    hideVendor = false,
}: PackageCardProps) {
    const [vendor, name] = splitPackageName(pkg.name);
    const source = hideRepository
        ? null
        : pkg.mirrorName
          ? { icon: Copy, label: pkg.mirrorName }
          : pkg.repositoryName
            ? { icon: GitBranch, label: pkg.repositoryName }
            : null;

    return (
        <>
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    <span className="truncate font-medium transition-colors group-hover:text-primary">
                        {vendor && !hideVendor && (
                            <span className="text-muted-foreground">
                                {vendor}/
                            </span>
                        )}
                        {name}
                    </span>
                    {pkg.sourcePath && (
                        <code className="shrink-0 rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-muted-foreground">
                            {pkg.sourcePath}
                        </code>
                    )}
                    {pkg.visibility === 'public' && (
                        <span className="inline-flex shrink-0 items-center gap-1 text-xs text-muted-foreground">
                            <Globe className="size-3" />
                            Public
                        </span>
                    )}
                </div>
                <p className="mt-0.5 truncate text-muted-foreground">
                    {pkg.description || (
                        <span className="text-muted-foreground/60 italic">
                            No description
                        </span>
                    )}
                </p>
            </div>
            <div className="flex shrink-0 items-center gap-5 text-muted-foreground">
                {source && (
                    <span className="hidden items-center gap-1.5 lg:inline-flex">
                        <source.icon className="size-3.5" />
                        <span className="max-w-40 truncate">
                            {source.label}
                        </span>
                    </span>
                )}
                <span className="hidden w-28 text-right whitespace-nowrap md:inline">
                    {DateTime.fromISO(pkg.updatedAt).toRelative()}
                </span>
                <span className="hidden w-24 text-right whitespace-nowrap tabular-nums sm:inline">
                    {pkg.versionsCount}{' '}
                    {pkg.versionsCount === 1 ? 'version' : 'versions'}
                </span>
                <span className="flex w-28 justify-end">
                    {pkg.latestVersion ? (
                        <span className="inline-flex items-center gap-1 rounded-md border bg-surface-inset px-1.5 py-0.5 font-mono text-xs text-foreground">
                            <Tag className="size-3 text-muted-foreground" />
                            {pkg.latestVersion}
                        </span>
                    ) : (
                        <span className="text-xs">dev only</span>
                    )}
                </span>
                <ArrowUpRight className="size-4 text-muted-foreground/50 transition-all group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-muted-foreground" />
            </div>
        </>
    );
}

export function splitPackageName(fullName: string): [string | null, string] {
    const slash = fullName.indexOf('/');

    return slash === -1
        ? [null, fullName]
        : [fullName.slice(0, slash), fullName.slice(slash + 1)];
}
