import { show } from '@/actions/App/Domains/Repository/Http/Controllers/RepositoryController';
import HeadingSmall from '@/components/heading-small';
import { DownloadChart } from '@/components/stats/download-chart';
import { VersionDownloads } from '@/components/stats/version-downloads';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useDebounce } from '@/hooks/use-debounce';
import AppLayout from '@/layouts/app-layout';
import { createOrganizationBreadcrumb } from '@/lib/breadcrumbs';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Calendar,
    Check,
    ChevronRight,
    Copy,
    Download,
    ExternalLink,
    GitBranch,
    GitCommit,
    Globe,
    Link2,
    Lock,
    Package as PackageIcon,
    Search,
    Terminal,
    Trash2,
    Users,
    X,
} from 'lucide-react';
import { DateTime } from 'luxon';
import { useEffect, useRef, useState } from 'react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;
type PackageData = App.Domains.Package.Contracts.Data.PackageData;
type PackageVersionData = App.Domains.Package.Contracts.Data.PackageVersionData;
type PackageDownloadStatsData =
    App.Domains.Package.Contracts.Data.PackageDownloadStatsData;
type PackageVersionDetailData =
    App.Domains.Package.Contracts.Data.PackageVersionDetailData;

interface PackageShowProps {
    organization: OrganizationData;
    package: PackageData;
    downloadStats: PackageDownloadStatsData;
    versions: {
        data: PackageVersionData[];
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        query: string;
        type: string;
    };
    composerRepositoryUrl: string;
    canManageVersions: boolean;
    activeVersion: PackageVersionDetailData | null;
}

function CopyButton({
    text,
    icon: Icon = Copy,
    tooltip = 'Copied!',
    variant = 'ghost',
}: {
    text: string;
    icon?: React.ComponentType<React.SVGProps<SVGSVGElement>>;
    tooltip?: string;
    variant?: 'ghost' | 'outline';
}) {
    const [copied, setCopied] = useState(false);
    const isOutline = variant === 'outline';

    const copyToClipboard = async () => {
        if (!navigator?.clipboard) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                setCopied(true);
                setTimeout(() => setCopied(false), 2000);
            } catch (err) {
                console.warn('Failed to copy text', err);
            } finally {
                document.body.removeChild(textArea);
            }
            return;
        }

        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch (err) {
            console.warn('Failed to copy text', err);
        }
    };

    return (
        <Tooltip open={copied}>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant={variant}
                    size="icon"
                    className={isOutline ? 'h-8 w-8' : 'h-6 w-6'}
                    onClick={copyToClipboard}
                >
                    {copied ? (
                        <Check
                            className={`text-green-600 dark:text-green-400 ${isOutline ? 'h-4 w-4' : 'h-3 w-3'}`}
                        />
                    ) : (
                        <Icon className={isOutline ? 'h-4 w-4' : 'h-3 w-3'} />
                    )}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{tooltip}</TooltipContent>
        </Tooltip>
    );
}

export default function PackageShow({
    organization,
    package: pkg,
    downloadStats,
    versions,
    filters,
    composerRepositoryUrl,
    canManageVersions,
    activeVersion,
}: PackageShowProps) {
    const { auth } = usePage<{
        auth: { organizations: OrganizationData[] };
    }>().props;

    const [queryFilter, setQueryFilter] = useState(filters.query);
    const [typeFilter, setTypeFilter] = useState(filters.type || 'all');
    const [page, setPage] = useState(versions.current_page);

    const debouncedQuery = useDebounce(queryFilter, 300);

    const isInitialMount = useRef(true);

    useEffect(() => {
        if (isInitialMount.current) {
            isInitialMount.current = false;

            return;
        }

        const params: Record<string, string> = {};
        if (debouncedQuery) {
            params.query = debouncedQuery;
        }
        if (typeFilter && typeFilter !== 'all') {
            params.type = typeFilter;
        }
        if (page > 1) {
            params.page = String(page);
        }

        router.get(
            `/organizations/${organization.slug}/packages/${pkg.uuid}`,
            params,
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    }, [debouncedQuery, typeFilter, page, organization.slug, pkg.uuid]);

    const hasActiveFilters = queryFilter !== '' || typeFilter !== 'all';

    const handleQueryChange = (value: string) => {
        setQueryFilter(value);
        setPage(1);
    };

    const handleTypeChange = (value: string) => {
        setTypeFilter(value);
        setPage(1);
    };

    const clearFilters = () => {
        setQueryFilter('');
        setTypeFilter('all');
        setPage(1);
    };

    const openVersion = (versionUuid: string) => {
        router.get(
            `/organizations/${organization.slug}/packages/${pkg.uuid}`,
            {
                ...Object.fromEntries(
                    new URLSearchParams(window.location.search),
                ),
                version: versionUuid,
            },
            { preserveScroll: true, preserveState: true },
        );
    };

    const closeVersionPanel = () => {
        const params = new URLSearchParams(window.location.search);
        params.delete('version');
        const query = Object.fromEntries(params);

        router.get(
            `/organizations/${organization.slug}/packages/${pkg.uuid}`,
            query,
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const breadcrumbs = [
        createOrganizationBreadcrumb(organization, auth.organizations),
        {
            title: 'Packages',
            href: `/organizations/${organization.slug}/packages`,
        },
        {
            title: pkg.name,
            href: `/organizations/${organization.slug}/packages/${pkg.uuid}`,
        },
    ];

    const composerConfig = `{
    "repositories": [
        {
            "type": "composer",
            "url": "${composerRepositoryUrl}"
        }
    ]
}`;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${pkg.name} - ${organization.name}`} />

            <div className="mx-auto w-7xl space-y-6 p-6">
                <div className="space-y-4">
                    <div className="flex items-start justify-between">
                        <div className="grid space-y-2">
                            <div className="flex items-center gap-3">
                                <h1 className="font-mono text-2xl font-semibold">
                                    {pkg.name}
                                </h1>
                                <Badge
                                    variant={
                                        pkg.visibility === 'private'
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                >
                                    {pkg.visibility === 'private' ? (
                                        <>
                                            <Lock className="mr-1 h-3 w-3" />
                                            Private
                                        </>
                                    ) : (
                                        <>
                                            <Globe className="mr-1 h-3 w-3" />
                                            Public
                                        </>
                                    )}
                                </Badge>
                            </div>
                            <div className="flex items-center gap-4 text-muted-foreground">
                                {pkg.repositoryIdentifier && (
                                    <span className="flex items-center gap-1.5">
                                        Repository:{' '}
                                        {pkg.repositoryUuid ? (
                                            <Link
                                                href={show.url([
                                                    organization.slug,
                                                    pkg.repositoryUuid,
                                                ])}
                                                className="flex items-center gap-1 font-medium text-primary hover:underline"
                                            >
                                                <GitBranch className="h-3.5 w-3.5" />
                                                {pkg.repositoryName}
                                            </Link>
                                        ) : (
                                            <span className="flex items-center gap-1 font-medium">
                                                <GitBranch className="h-3.5 w-3.5" />
                                                {pkg.repositoryName}
                                            </span>
                                        )}
                                    </span>
                                )}
                                <span>
                                    {versions.total} version
                                    {versions.total === 1 ? '' : 's'}
                                </span>
                                <span className="flex items-center gap-1">
                                    <Download className="h-3.5 w-3.5" />
                                    {downloadStats.totalDownloads.toLocaleString()}{' '}
                                    download
                                    {downloadStats.totalDownloads === 1
                                        ? ''
                                        : 's'}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Composer Configuration</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-muted-foreground">
                            Add this repository to your{' '}
                            <code className="rounded bg-muted px-1 py-0.5 text-xs">
                                composer.json
                            </code>{' '}
                            to install packages from this organization:
                        </p>
                        <div className="relative">
                            <pre className="overflow-x-auto rounded bg-muted p-4 text-sm">
                                <code>{composerConfig}</code>
                            </pre>
                            <div className="absolute top-2 right-2">
                                <CopyButton text={composerConfig} />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <DownloadChart
                        title="Downloads (Last 30 Days)"
                        data={downloadStats.dailyDownloads}
                        compact
                    />
                    <VersionDownloads data={downloadStats.versionBreakdown} />
                </div>

                <div className="space-y-4">
                    <HeadingSmall
                        title="Versions"
                        description={`${versions.total} version${versions.total === 1 ? '' : 's'} available`}
                    />

                    <div className="flex flex-wrap items-center gap-3">
                        <div className="relative min-w-48 flex-1">
                            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                placeholder="Filter by version or source hash..."
                                value={queryFilter}
                                onChange={(e) =>
                                    handleQueryChange(e.target.value)
                                }
                                className="pl-9"
                            />
                        </div>
                        <Select
                            value={typeFilter}
                            onValueChange={handleTypeChange}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="All types" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All types</SelectItem>
                                <SelectItem value="stable">Stable</SelectItem>
                                <SelectItem value="dev">Dev</SelectItem>
                            </SelectContent>
                        </Select>
                        {hasActiveFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={clearFilters}
                            >
                                <X className="mr-1 h-4 w-4" />
                                Clear filters
                            </Button>
                        )}
                    </div>

                    {versions.data.length === 0 ? (
                        <Card>
                            <CardContent className="py-8 text-center text-muted-foreground">
                                {hasActiveFilters
                                    ? 'No versions match the current filters.'
                                    : 'No versions available yet.'}
                            </CardContent>
                        </Card>
                    ) : (
                        <>
                            <div className="rounded-lg border bg-card">
                                {versions.data.map((version, index) => (
                                    <button
                                        key={version.uuid}
                                        type="button"
                                        onClick={() =>
                                            openVersion(version.uuid)
                                        }
                                        className={`flex w-full items-center gap-4 px-5 py-4 text-left transition-colors hover:bg-muted/50 ${index < versions.data.length - 1 ? 'border-b' : ''}`}
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <code className="truncate font-mono text-sm font-medium">
                                                    {version.version}
                                                </code>
                                                {version.version.includes(
                                                    'dev',
                                                ) ||
                                                version.normalizedVersion.startsWith(
                                                    'dev-',
                                                ) ? (
                                                    <Badge variant="secondary">
                                                        dev
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="success">
                                                        stable
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="mt-1 flex items-center gap-3 text-sm text-muted-foreground">
                                                {version.releasedAt && (
                                                    <span className="flex items-center gap-1">
                                                        <Calendar className="h-3.5 w-3.5" />
                                                        {DateTime.fromISO(
                                                            version.releasedAt,
                                                        ).toRelative()}
                                                        <span>&middot;</span>
                                                        {DateTime.fromISO(
                                                            version.releasedAt,
                                                        ).toLocaleString(
                                                            DateTime.DATETIME_MED,
                                                        )}
                                                    </span>
                                                )}
                                                {version.sourceReference && (
                                                    <span className="flex items-center gap-1 font-mono">
                                                        <GitCommit className="h-3.5 w-3.5" />
                                                        {version.sourceReference.substring(
                                                            0,
                                                            7,
                                                        )}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    </button>
                                ))}
                            </div>

                            {versions.last_page > 1 && (
                                <div className="flex items-center justify-between">
                                    <div className="text-muted-foreground">
                                        Showing{' '}
                                        {(versions.current_page - 1) *
                                            versions.per_page +
                                            1}{' '}
                                        to{' '}
                                        {Math.min(
                                            versions.current_page *
                                                versions.per_page,
                                            versions.total,
                                        )}{' '}
                                        of {versions.total} versions
                                    </div>
                                    <div className="flex gap-2">
                                        {versions.links.map((link, index) => {
                                            if (
                                                link.url === null ||
                                                link.label === '...'
                                            ) {
                                                return (
                                                    <span
                                                        key={index}
                                                        className="px-3 py-2 text-muted-foreground"
                                                    >
                                                        <span
                                                            dangerouslySetInnerHTML={{
                                                                __html: link.label,
                                                            }}
                                                        />
                                                    </span>
                                                );
                                            }

                                            const pageNumber = new URL(
                                                link.url,
                                            ).searchParams.get('page');

                                            return (
                                                <button
                                                    key={index}
                                                    type="button"
                                                    onClick={() =>
                                                        setPage(
                                                            pageNumber
                                                                ? Number(
                                                                      pageNumber,
                                                                  )
                                                                : 1,
                                                        )
                                                    }
                                                    className={`rounded px-3 py-2 transition-colors ${
                                                        link.active
                                                            ? 'bg-primary text-primary-foreground'
                                                            : 'bg-card text-muted-foreground/110 hover:bg-muted/80'
                                                    }`}
                                                >
                                                    <span
                                                        dangerouslySetInnerHTML={{
                                                            __html: link.label,
                                                        }}
                                                    />
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>

            <Sheet
                open={activeVersion !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        closeVersionPanel();
                    }
                }}
            >
                <SheetContent className="overflow-y-auto sm:max-w-xl [&>button.absolute]:hidden">
                    {activeVersion && (
                        <>
                            <SheetHeader className="p-6">
                                <div className="flex items-start justify-between gap-4">
                                    <div className="flex min-w-0 items-start gap-3">
                                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <PackageIcon className="h-5 w-5" />
                                        </div>
                                        <div className="min-w-0 space-y-1">
                                            <SheetTitle className="truncate font-mono text-lg">
                                                {activeVersion.version}
                                            </SheetTitle>
                                            <div className="flex items-center gap-2">
                                                {activeVersion.isDev ? (
                                                    <Badge variant="secondary">
                                                        dev
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="success">
                                                        stable
                                                    </Badge>
                                                )}
                                                {activeVersion.description && (
                                                    <SheetDescription className="truncate">
                                                        {
                                                            activeVersion.description
                                                        }
                                                    </SheetDescription>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <CopyButton
                                            text={`${window.location.origin}/organizations/${organization.slug}/packages/${pkg.uuid}?version=${activeVersion.uuid}`}
                                            icon={Link2}
                                            tooltip="Link copied!"
                                            variant="outline"
                                        />
                                        <SheetClose asChild>
                                            <Button
                                                variant="outline"
                                                size="icon"
                                                className="h-8 w-8"
                                            >
                                                <X className="h-4 w-4" />
                                                <span className="sr-only">
                                                    Close
                                                </span>
                                            </Button>
                                        </SheetClose>
                                    </div>
                                </div>
                            </SheetHeader>

                            <div className="space-y-6 p-6 pt-0">
                                {/* Quick info row */}
                                <div className="grid grid-cols-2 gap-4">
                                    {activeVersion.releasedAt && (
                                        <div className="rounded-lg border p-4">
                                            <div className="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-muted-foreground">
                                                <Calendar className="h-4 w-4" />
                                                Released
                                            </div>
                                            <p className="font-medium">
                                                {DateTime.fromISO(
                                                    activeVersion.releasedAt,
                                                ).toLocaleString(
                                                    DateTime.DATETIME_MED,
                                                )}
                                            </p>
                                        </div>
                                    )}
                                    {activeVersion.sourceReference && (
                                        <div className="rounded-lg border p-4">
                                            <div className="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-muted-foreground">
                                                <GitCommit className="h-4 w-4" />
                                                Commit
                                            </div>
                                            {activeVersion.commitUrl ? (
                                                <a
                                                    href={
                                                        activeVersion.commitUrl
                                                    }
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex items-center gap-1.5 font-mono font-medium text-primary transition-colors hover:underline"
                                                >
                                                    {activeVersion.sourceReference.substring(
                                                        0,
                                                        7,
                                                    )}
                                                    <ExternalLink className="h-3.5 w-3.5" />
                                                </a>
                                            ) : (
                                                <code className="font-mono font-medium">
                                                    {activeVersion.sourceReference.substring(
                                                        0,
                                                        7,
                                                    )}
                                                </code>
                                            )}
                                        </div>
                                    )}
                                </div>

                                {/* Install command */}
                                <div>
                                    <div className="mb-2 flex items-center gap-1.5 text-sm font-medium text-muted-foreground">
                                        <Terminal className="h-4 w-4" />
                                        Install
                                    </div>
                                    <div className="flex items-center gap-2 rounded-lg border bg-muted/50 px-4 py-3">
                                        <code className="flex-1 truncate font-mono text-sm">
                                            composer require {pkg.name}:
                                            {activeVersion.version}
                                        </code>
                                        <CopyButton
                                            text={`composer require ${pkg.name}:${activeVersion.version}`}
                                        />
                                    </div>
                                </div>

                                {/* Metadata section */}
                                {(activeVersion.license ||
                                    activeVersion.type ||
                                    activeVersion.authors ||
                                    activeVersion.keywords) && (
                                    <>
                                        <Separator />
                                        <div className="space-y-4">
                                            {(activeVersion.license ||
                                                activeVersion.type) && (
                                                <div className="grid grid-cols-2 gap-4">
                                                    {activeVersion.license && (
                                                        <div>
                                                            <span className="text-sm font-medium text-muted-foreground">
                                                                License
                                                            </span>
                                                            <p className="mt-0.5 font-medium">
                                                                {
                                                                    activeVersion.license
                                                                }
                                                            </p>
                                                        </div>
                                                    )}
                                                    {activeVersion.type && (
                                                        <div>
                                                            <span className="text-sm font-medium text-muted-foreground">
                                                                Type
                                                            </span>
                                                            <p className="mt-0.5 font-medium">
                                                                {
                                                                    activeVersion.type
                                                                }
                                                            </p>
                                                        </div>
                                                    )}
                                                </div>
                                            )}

                                            {activeVersion.authors &&
                                                activeVersion.authors.length >
                                                    0 && (
                                                    <div>
                                                        <div className="mb-2 flex items-center gap-1.5 text-sm font-medium text-muted-foreground">
                                                            <Users className="h-4 w-4" />
                                                            Authors
                                                        </div>
                                                        <div className="flex flex-wrap gap-1.5">
                                                            {activeVersion.authors.map(
                                                                (author, i) => (
                                                                    <Badge
                                                                        key={i}
                                                                        variant="secondary"
                                                                    >
                                                                        {author.name ||
                                                                            author.email}
                                                                    </Badge>
                                                                ),
                                                            )}
                                                        </div>
                                                    </div>
                                                )}

                                            {activeVersion.keywords &&
                                                activeVersion.keywords.length >
                                                    0 && (
                                                    <div>
                                                        <span className="mb-2 block text-sm font-medium text-muted-foreground">
                                                            Keywords
                                                        </span>
                                                        <div className="flex flex-wrap gap-1.5">
                                                            {activeVersion.keywords.map(
                                                                (keyword) => (
                                                                    <Badge
                                                                        key={
                                                                            keyword
                                                                        }
                                                                        variant="outline"
                                                                    >
                                                                        {
                                                                            keyword
                                                                        }
                                                                    </Badge>
                                                                ),
                                                            )}
                                                        </div>
                                                    </div>
                                                )}
                                        </div>
                                    </>
                                )}
                            </div>

                            {canManageVersions && (
                                <SheetFooter className="border-t p-6">
                                    <Dialog>
                                        <DialogTrigger asChild>
                                            <Button
                                                variant="destructive"
                                                className="w-full"
                                            >
                                                <Trash2 className="mr-2 h-4 w-4" />
                                                Delete version
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogTitle>
                                                Delete version{' '}
                                                {activeVersion.version}?
                                            </DialogTitle>
                                            <DialogDescription>
                                                This will permanently remove
                                                version{' '}
                                                <strong>
                                                    {activeVersion.version}
                                                </strong>{' '}
                                                from {pkg.name}. This action
                                                cannot be undone.
                                            </DialogDescription>
                                            <DialogFooter className="gap-2">
                                                <DialogClose asChild>
                                                    <Button variant="secondary">
                                                        Cancel
                                                    </Button>
                                                </DialogClose>
                                                <Button
                                                    variant="destructive"
                                                    onClick={() =>
                                                        router.delete(
                                                            `/organizations/${organization.slug}/packages/${pkg.uuid}/versions/${activeVersion.uuid}`,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Delete version
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                </SheetFooter>
                            )}
                        </>
                    )}
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
