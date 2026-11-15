import { store as uploadPackage } from '@/actions/App/Domains/Package/Http/Controllers/ArtifactController';
import {
    index,
    show,
} from '@/actions/App/Domains/Package/Http/Controllers/PackageController';
import { EmptyState } from '@/components/empty-state';
import HeadingSmall from '@/components/heading-small';
import PackageCard, { splitPackageName } from '@/components/package-card';
import { Button } from '@/components/ui/button';
import { CardList } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import UploadPackageDialog from '@/components/upload-package-dialog';
import { useDebounce } from '@/hooks/use-debounce';
import AppLayout from '@/layouts/app-layout';
import { createOrganizationBreadcrumb } from '@/lib/breadcrumbs';
import { cn } from '@/lib/utils';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Copy,
    FileArchive,
    GitBranch,
    type LucideIcon,
    Package,
    Search,
    SearchX,
    ShieldAlert,
    TriangleAlert,
    Upload,
    X,
} from 'lucide-react';
import { type ReactNode, useEffect, useMemo, useState } from 'react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;
type PackageData = App.Domains.Package.Contracts.Data.PackageData;
type PackageFiltersData = App.Domains.Package.Contracts.Data.PackageFiltersData;
type PackageFacetsData = App.Domains.Package.Contracts.Data.PackageFacetsData;
type PackageSourceOptionData =
    App.Domains.Package.Contracts.Data.PackageSourceOptionData;
type PackageStatus = App.Domains.Package.Contracts.Enums.PackageStatus;

interface PaginatedPackages {
    data: PackageData[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

interface PackagesPageProps {
    organization: OrganizationData;
    packages: PaginatedPackages;
    filters: PackageFiltersData;
    facets: PackageFacetsData;
    canUploadPackages: boolean;
    maxUploadSize: number;
}

const SOURCE_ICONS: Record<PackageSourceOptionData['source'], LucideIcon> = {
    repository: GitBranch,
    upload: FileArchive,
    mirror: Copy,
};

function sourceKey(source: string | null, mirror: string | null): string {
    return mirror ?? source ?? 'all';
}

export default function Packages({
    organization,
    packages,
    filters,
    facets,
    canUploadPackages,
    maxUploadSize,
}: PackagesPageProps) {
    const { auth } = usePage<{
        auth: { organizations: OrganizationData[] };
    }>().props;

    const [query, setQuery] = useState(filters.query);
    const [source, setSource] = useState(
        sourceKey(filters.source, filters.mirror),
    );
    const [status, setStatus] = useState<PackageStatus | null>(filters.status);
    const [isLoading, setIsLoading] = useState(false);
    const [isUploadDialogOpen, setIsUploadDialogOpen] = useState(false);
    const debouncedQuery = useDebounce(query, 250);

    const visit = (next: {
        query?: string;
        source?: string;
        status?: PackageStatus | null;
    }) => {
        const nextQuery = (next.query ?? query).trim();
        const nextSource = next.source ?? source;
        const nextStatus = next.status === undefined ? status : next.status;
        const option = facets.sources.find(
            (option) => sourceKey(option.source, option.mirror) === nextSource,
        );
        const params: Record<string, string> = {};

        if (nextQuery !== '') {
            params.query = nextQuery;
        }
        if (option?.mirror) {
            params.mirror = option.mirror;
        } else if (nextSource !== 'all') {
            params.source = nextSource;
        }
        if (nextStatus) {
            params.status = nextStatus;
        }

        router.get(index.url(organization.slug), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setIsLoading(true),
            onFinish: () => setIsLoading(false),
        });
    };

    useEffect(() => {
        if (debouncedQuery.trim() !== filters.query) {
            visit({ query: debouncedQuery });
        }
        // Only the debounced search text should trigger a visit here; the
        // other filters visit directly from their handlers.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [debouncedQuery]);

    const changeSource = (value: string) => {
        setSource(value);
        visit({ source: value });
    };

    const toggleStatus = (value: PackageStatus) => {
        const next = status === value ? null : value;
        setStatus(next);
        visit({ status: next });
    };

    const clearFilters = () => {
        setQuery('');
        setSource('all');
        setStatus(null);
        visit({ query: '', source: 'all', status: null });
    };

    const hasActiveFilters =
        query.trim() !== '' || source !== 'all' || status !== null;

    const vendors = useMemo(() => {
        const groups = new Map<string, PackageData[]>();

        packages.data.forEach((pkg) => {
            const vendor = splitPackageName(pkg.name)[0] ?? '';
            groups.set(vendor, [...(groups.get(vendor) ?? []), pkg]);
        });

        return [...groups.entries()];
    }, [packages.data]);

    const statusOptions = [
        {
            value: 'vulnerable' as const,
            label: 'Advisories',
            count: facets.vulnerable,
            icon: (
                <ShieldAlert className="size-3.5 text-red-600 dark:text-red-400" />
            ),
        },
        {
            value: 'sync_failed' as const,
            label: 'Sync failed',
            count: facets.syncFailed,
            icon: (
                <TriangleAlert className="size-3.5 text-amber-600 dark:text-amber-400" />
            ),
        },
    ].filter((option) => option.count > 0 || status === option.value);

    const breadcrumbs = [
        createOrganizationBreadcrumb(organization, auth.organizations),
        {
            title: 'Packages',
            href: index.url(organization.slug),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Packages - ${organization.name}`} />

            <div className="mx-auto w-full max-w-7xl space-y-6 p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <HeadingSmall
                        title="Packages"
                        description={
                            facets.total > 0
                                ? `${facets.total.toLocaleString()} ${facets.total === 1 ? 'package' : 'packages'} in this organization`
                                : 'Composer packages in this organization'
                        }
                    />
                    <div className="flex items-center gap-2">
                        {canUploadPackages && (
                            <Button
                                variant="secondary"
                                onClick={() => setIsUploadDialogOpen(true)}
                            >
                                <Upload className="h-4 w-4" />
                                Upload Package
                            </Button>
                        )}
                        <Button asChild>
                            <Link
                                href={`/organizations/${organization.slug}/repositories`}
                            >
                                <GitBranch className="h-4 w-4" />
                                Manage Repositories
                            </Link>
                        </Button>
                    </div>
                </div>

                {facets.total === 0 ? (
                    <EmptyState
                        icon={Package}
                        title="No packages yet"
                        description={
                            canUploadPackages
                                ? 'Connect a Git repository to automatically discover and sync Composer packages, or upload a package archive.'
                                : 'Connect a Git repository to automatically discover and sync Composer packages.'
                        }
                        action={{
                            label: 'Connect Your First Repository',
                            href: `/organizations/${organization.slug}/repositories`,
                        }}
                    />
                ) : (
                    <>
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="relative w-full sm:max-w-xs">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="search"
                                    aria-label="Search packages"
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    placeholder="Search by name or description"
                                    className="h-9 pl-9 [&::-webkit-search-cancel-button]:appearance-none"
                                />
                            </div>

                            {facets.sources.length > 1 && (
                                <Segmented label="Source">
                                    <Segment
                                        active={source === 'all'}
                                        onClick={() => changeSource('all')}
                                        count={facets.total}
                                    >
                                        All
                                    </Segment>
                                    {facets.sources.map((option) => {
                                        const key = sourceKey(
                                            option.source,
                                            option.mirror,
                                        );
                                        const Icon =
                                            SOURCE_ICONS[option.source];

                                        return (
                                            <Segment
                                                key={key}
                                                active={source === key}
                                                onClick={() =>
                                                    changeSource(key)
                                                }
                                                count={option.count}
                                            >
                                                <Icon className="hidden size-3.5 opacity-70 sm:block" />
                                                {option.label}
                                            </Segment>
                                        );
                                    })}
                                </Segmented>
                            )}

                            {statusOptions.length > 0 && (
                                <div
                                    role="group"
                                    aria-label="Status"
                                    className="flex items-center gap-1.5"
                                >
                                    {statusOptions.map((option) => (
                                        <button
                                            key={option.value}
                                            type="button"
                                            aria-pressed={
                                                status === option.value
                                            }
                                            onClick={() =>
                                                toggleStatus(option.value)
                                            }
                                            className={cn(
                                                'inline-flex h-9 items-center gap-1.5 rounded-lg border px-3 font-medium transition-colors',
                                                status === option.value
                                                    ? 'border-ring bg-card text-foreground shadow-xs'
                                                    : 'border-border bg-transparent text-muted-foreground hover:bg-accent hover:text-foreground',
                                            )}
                                        >
                                            {option.icon}
                                            {option.label}
                                            <CountPill
                                                active={status === option.value}
                                            >
                                                {option.count}
                                            </CountPill>
                                        </button>
                                    ))}
                                </div>
                            )}

                            {hasActiveFilters && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={clearFilters}
                                >
                                    <X className="size-4" />
                                    Clear
                                </Button>
                            )}
                        </div>

                        <div
                            className={cn(
                                'transition-opacity duration-150',
                                isLoading && 'opacity-60',
                            )}
                        >
                            {packages.data.length === 0 ? (
                                <EmptyState
                                    icon={SearchX}
                                    title="No matching packages"
                                    description="Try a different search, source or status."
                                    action={{
                                        label: 'Clear filters',
                                        onClick: clearFilters,
                                    }}
                                    className="py-12"
                                />
                            ) : (
                                <div className="space-y-6">
                                    {vendors.map(([vendor, vendorPackages]) => (
                                        <section
                                            key={vendor}
                                            className="space-y-2"
                                        >
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
                        </div>

                        {packages.total > 0 && (
                            <Pagination packages={packages} />
                        )}
                    </>
                )}
            </div>

            {canUploadPackages && (
                <UploadPackageDialog
                    action={uploadPackage.url(organization.slug)}
                    title="Upload Package"
                    description="Publish a package from a zip archive, without a Git repository. The package name comes from its composer.json. Once it exists, CI can publish new versions with a token that can publish."
                    maxUploadSize={maxUploadSize}
                    isOpen={isUploadDialogOpen}
                    onClose={() => setIsUploadDialogOpen(false)}
                />
            )}
        </AppLayout>
    );
}

function Segmented({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div
            role="radiogroup"
            aria-label={label}
            className="inline-flex h-9 max-w-full [scrollbar-width:none] items-center overflow-x-auto rounded-lg border bg-surface-inset p-0.5"
        >
            {children}
        </div>
    );
}

function Segment({
    active,
    onClick,
    count,
    children,
}: {
    active: boolean;
    onClick: () => void;
    count: number;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            role="radio"
            aria-checked={active}
            onClick={onClick}
            className={cn(
                'inline-flex shrink-0 items-center gap-1.5 rounded-md px-2.5 py-1 font-medium whitespace-nowrap transition-colors',
                active
                    ? 'bg-card text-foreground shadow-xs'
                    : 'text-muted-foreground hover:text-foreground',
            )}
        >
            {children}
            <CountPill active={active}>{count}</CountPill>
        </button>
    );
}

function CountPill({
    active,
    children,
}: {
    active: boolean;
    children: number;
}) {
    return (
        <span
            className={cn(
                'rounded-full px-1.5 text-xs tabular-nums transition-colors',
                active
                    ? 'bg-primary/10 text-primary dark:bg-primary/15'
                    : 'bg-muted text-muted-foreground',
            )}
        >
            {children.toLocaleString()}
        </span>
    );
}

function Pagination({ packages }: { packages: PaginatedPackages }) {
    const pages = packages.links.slice(1, -1);
    const previous = packages.links[0];
    const next = packages.links[packages.links.length - 1];

    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-muted-foreground tabular-nums">
                {packages.last_page > 1 ? (
                    <>
                        Showing {packages.from?.toLocaleString()}–
                        {packages.to?.toLocaleString()} of{' '}
                        {packages.total.toLocaleString()} packages
                    </>
                ) : (
                    <>
                        {packages.total.toLocaleString()}{' '}
                        {packages.total === 1 ? 'package' : 'packages'}
                    </>
                )}
            </p>
            {packages.last_page > 1 && (
                <nav
                    aria-label="Pagination"
                    className="inline-flex h-9 items-center rounded-lg border bg-surface-inset p-0.5"
                >
                    <PageLink url={previous?.url ?? null} label="Previous page">
                        <ChevronLeft className="size-4" />
                    </PageLink>
                    {pages.map((link, position) =>
                        link.url === null ? (
                            <span
                                key={`gap-${position}`}
                                className="px-1.5 text-muted-foreground"
                            >
                                &hellip;
                            </span>
                        ) : (
                            <PageLink
                                key={link.label}
                                url={link.url}
                                active={link.active}
                                label={`Page ${link.label}`}
                            >
                                {link.label}
                            </PageLink>
                        ),
                    )}
                    <PageLink url={next?.url ?? null} label="Next page">
                        <ChevronRight className="size-4" />
                    </PageLink>
                </nav>
            )}
        </div>
    );
}

function PageLink({
    url,
    active = false,
    label,
    children,
}: {
    url: string | null;
    active?: boolean;
    label: string;
    children: ReactNode;
}) {
    const className = cn(
        'inline-flex h-7 min-w-7 items-center justify-center rounded-md px-2 font-medium tabular-nums transition-colors',
        active
            ? 'bg-card text-foreground shadow-xs'
            : 'text-muted-foreground hover:text-foreground',
    );

    if (url === null) {
        return (
            <span
                aria-hidden
                className={cn(className, 'pointer-events-none opacity-40')}
            >
                {children}
            </span>
        );
    }

    return (
        <Link
            href={url}
            aria-label={label}
            aria-current={active ? 'page' : undefined}
            preserveState
            className={className}
        >
            {children}
        </Link>
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
