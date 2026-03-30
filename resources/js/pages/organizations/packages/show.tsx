import { show } from '@/actions/App/Domains/Repository/Http/Controllers/RepositoryController';
import { CopyButton } from '@/components/copy-button';
import HeadingSmall from '@/components/heading-small';
import { VersionDownloadChart } from '@/components/stats/version-download-chart';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardList,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useDebounce } from '@/hooks/use-debounce';
import AppLayout from '@/layouts/app-layout';
import { createOrganizationBreadcrumb } from '@/lib/breadcrumbs';
import { formatBytes } from '@/lib/utils';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Calendar,
    Check,
    ChevronRight,
    Copy,
    Download,
    EllipsisVertical,
    ExternalLink,
    GitBranch,
    GitCommit,
    Globe,
    HardDrive,
    Link2,
    Lock,
    Package as PackageIcon,
    Search,
    ShieldAlert,
    Tag,
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
type SecurityAdvisoryMatchData =
    App.Domains.Security.Contracts.Data.SecurityAdvisoryMatchData;

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
    canManageVersions: boolean;
    canDeletePackage: boolean;
    activeVersion: PackageVersionDetailData | null;
}

function CopyInstallButton({ text }: { text: string }) {
    const [copied, setCopied] = useState(false);
    const [tooltipOpen, setTooltipOpen] = useState(false);

    const copyToClipboard = (e: React.MouseEvent) => {
        e.stopPropagation();
        if (navigator?.clipboard) {
            navigator.clipboard.writeText(text);
        } else {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
        }
        setCopied(true);
        setTooltipOpen(false);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <Tooltip
            open={tooltipOpen}
            onOpenChange={(open) => setTooltipOpen(copied ? false : open)}
        >
            <TooltipTrigger asChild>
                <button
                    type="button"
                    onClick={copyToClipboard}
                    className="inline-flex shrink-0 items-center gap-1.5 rounded-md border px-2 py-1 text-xs text-muted-foreground opacity-0 transition-opacity group-hover/version:opacity-100 hover:bg-muted hover:text-foreground"
                >
                    {copied ? (
                        <Check className="h-3 w-3 text-green-600 dark:text-green-400" />
                    ) : (
                        <Terminal className="h-3 w-3" />
                    )}
                    <span className="font-mono">
                        {copied ? 'Copied!' : 'composer require'}
                    </span>
                </button>
            </TooltipTrigger>
            <TooltipContent>Copy install command</TooltipContent>
        </Tooltip>
    );
}

export default function PackageShow({
    organization,
    package: pkg,
    downloadStats,
    versions,
    filters,
    canManageVersions,
    canDeletePackage,
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

    const composerRepoCommand = `composer config repositories.${organization.slug} composer ${organization.composerRepositoryUrl}`;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${pkg.name} - ${organization.name}`} />

            <div className="mx-auto w-7xl space-y-6 p-6">
                <div className="space-y-4">
                    <div className="flex items-start justify-between">
                        <div className="space-y-1">
                            <div className="flex items-center gap-3">
                                <HeadingSmall title={pkg.name} />
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
                                {pkg.mirrorName && (
                                    <Link
                                        href={`/organizations/${organization.slug}/settings/mirrors`}
                                        className="flex items-center gap-1.5 hover:text-foreground"
                                    >
                                        <Copy className="h-3.5 w-3.5" />
                                        <span>
                                            Mirror:{' '}
                                            <span className="font-medium">
                                                {pkg.mirrorName}
                                            </span>
                                        </span>
                                    </Link>
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
                        {canDeletePackage && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="secondary">
                                        Actions
                                        <EllipsisVertical className="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <Dialog>
                                        <DialogTrigger asChild>
                                            <DropdownMenuItem
                                                onSelect={(e) =>
                                                    e.preventDefault()
                                                }
                                                variant="destructive"
                                            >
                                                <Trash2 />
                                                Delete package
                                            </DropdownMenuItem>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogTitle>
                                                Delete {pkg.name}?
                                            </DialogTitle>
                                            <DialogDescription>
                                                This will permanently remove the
                                                package{' '}
                                                <strong>{pkg.name}</strong> and
                                                all {versions.total} version
                                                {versions.total === 1
                                                    ? ''
                                                    : 's'}
                                                . This action cannot be undone.
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
                                                            `/organizations/${organization.slug}/packages/${pkg.uuid}`,
                                                        )
                                                    }
                                                >
                                                    Delete package
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Composer Configuration</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-muted-foreground">
                            Add this repository to your project to install
                            packages from this organization:
                        </p>
                        <div className="flex items-center gap-2 rounded-lg border bg-muted/50 px-4 py-3">
                            <code className="flex-1 truncate font-mono text-sm">
                                {composerRepoCommand}
                            </code>
                            <CopyButton text={composerRepoCommand} />
                        </div>
                    </CardContent>
                </Card>

                <VersionDownloadChart
                    title="Downloads (Last 30 Days)"
                    versionData={downloadStats.versionDailyDownloads}
                    fallbackData={downloadStats.dailyDownloads}
                />

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
                            <CardList>
                                {versions.data.map((version, index) => (
                                    <div
                                        key={version.uuid}
                                        role="button"
                                        tabIndex={0}
                                        onClick={() =>
                                            openVersion(version.uuid)
                                        }
                                        className={`group/version flex w-full cursor-pointer items-center gap-4 px-5 py-4 text-left transition-colors hover:bg-muted/50 ${index < versions.data.length - 1 ? 'border-b' : ''}`}
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="truncate font-medium tabular-nums">
                                                    {version.version}
                                                </span>
                                                {version.vulnerabilityCount >
                                                    0 && (
                                                    <span
                                                        className={`inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-xs ${
                                                            version.highestSeverity ===
                                                                'critical' ||
                                                            version.highestSeverity ===
                                                                'high'
                                                                ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-400'
                                                                : version.highestSeverity ===
                                                                    'medium'
                                                                  ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400'
                                                                  : 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-400'
                                                        }`}
                                                    >
                                                        <ShieldAlert className="h-3 w-3" />
                                                        {
                                                            version.vulnerabilityCount
                                                        }{' '}
                                                        vulnerabilit
                                                        {version.vulnerabilityCount ===
                                                        1
                                                            ? 'y'
                                                            : 'ies'}
                                                    </span>
                                                )}
                                                {version.distSize !== null && (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <span className="inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-xs text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-400">
                                                                <HardDrive className="h-3 w-3" />
                                                                {formatBytes(
                                                                    version.distSize,
                                                                )}
                                                            </span>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            Mirror stored and
                                                            served by Pricore
                                                        </TooltipContent>
                                                    </Tooltip>
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
                                                {version.tagUrl && (
                                                    <a
                                                        href={
                                                            version.tagUrl
                                                        }
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        onClick={(e) =>
                                                            e.stopPropagation()
                                                        }
                                                        className="flex items-center gap-1 hover:text-foreground hover:underline"
                                                    >
                                                        <Tag className="h-3.5 w-3.5" />
                                                        {version.sourceTag}
                                                    </a>
                                                )}
                                            </div>
                                        </div>
                                        <CopyInstallButton
                                            text={`composer require ${pkg.name}:${version.version}`}
                                        />
                                        <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    </div>
                                ))}
                            </CardList>

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

            <Dialog
                open={activeVersion !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        closeVersionPanel();
                    }
                }}
            >
                <DialogContent className="max-h-[85vh] overflow-x-hidden overflow-y-auto sm:max-w-xl [&>*]:min-w-0 [&>button.absolute]:hidden">
                    {activeVersion && (
                        <>
                            <div className="flex min-w-0 items-start justify-between gap-4">
                                <div className="flex min-w-0 items-center gap-3">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <PackageIcon className="h-5 w-5" />
                                    </div>
                                    <div className="min-w-0 space-y-0.5">
                                        <DialogTitle className="truncate text-lg">
                                            {activeVersion.version}
                                        </DialogTitle>
                                        {activeVersion.description && (
                                            <DialogDescription className="line-clamp-2">
                                                {activeVersion.description}
                                            </DialogDescription>
                                        )}
                                    </div>
                                </div>
                                <CopyButton
                                    className="shrink-0"
                                    text={`${window.location.origin}/organizations/${organization.slug}/packages/${pkg.uuid}?version=${activeVersion.uuid}`}
                                    icon={Link2}
                                    tooltip="Link copied!"
                                    variant="outline"
                                />
                            </div>

                            <div className="mt-3 space-y-6">
                                {/* Quick info row */}
                                <div className="grid grid-cols-2 gap-4">
                                    {activeVersion.releasedAt && (
                                        <div>
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
                                        <div>
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
                                    {activeVersion.tagUrl && (
                                        <div>
                                            <div className="mb-1.5 flex items-center gap-1.5 text-sm font-medium text-muted-foreground">
                                                <Tag className="h-4 w-4" />
                                                Tag
                                            </div>
                                            <a
                                                href={
                                                    activeVersion.tagUrl
                                                }
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1.5 font-medium text-muted-foreground transition-colors hover:text-foreground hover:underline"
                                            >
                                                {activeVersion.sourceTag}
                                                <ExternalLink className="h-3.5 w-3.5" />
                                            </a>
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

                                {activeVersion.advisoryMatches &&
                                    activeVersion.advisoryMatches.length >
                                        0 && (
                                        <>
                                            <Separator />
                                            <div className="space-y-3">
                                                <div className="flex items-center gap-1.5 text-sm font-medium text-muted-foreground">
                                                    <ShieldAlert className="h-4 w-4 text-red-500" />
                                                    Security Advisories
                                                </div>
                                                <div className="space-y-2">
                                                    {activeVersion.advisoryMatches.map(
                                                        (
                                                            match: SecurityAdvisoryMatchData,
                                                        ) => (
                                                            <div
                                                                key={match.uuid}
                                                                className="rounded-lg border p-3"
                                                            >
                                                                <div className="flex items-start justify-between gap-2">
                                                                    <div className="min-w-0 flex-1">
                                                                        <div className="flex items-center gap-2">
                                                                            {match
                                                                                .advisory
                                                                                .link ? (
                                                                                <a
                                                                                    href={
                                                                                        match
                                                                                            .advisory
                                                                                            .link
                                                                                    }
                                                                                    target="_blank"
                                                                                    rel="noopener noreferrer"
                                                                                    className="text-sm font-medium text-primary hover:underline"
                                                                                >
                                                                                    {
                                                                                        match
                                                                                            .advisory
                                                                                            .title
                                                                                    }
                                                                                </a>
                                                                            ) : (
                                                                                <span className="text-sm font-medium">
                                                                                    {
                                                                                        match
                                                                                            .advisory
                                                                                            .title
                                                                                    }
                                                                                </span>
                                                                            )}
                                                                        </div>
                                                                        <div className="mt-1 flex flex-wrap items-center gap-1.5">
                                                                            <Badge
                                                                                variant={
                                                                                    match
                                                                                        .advisory
                                                                                        .severity ===
                                                                                        'critical' ||
                                                                                    match
                                                                                        .advisory
                                                                                        .severity ===
                                                                                        'high'
                                                                                        ? 'destructive'
                                                                                        : 'secondary'
                                                                                }
                                                                            >
                                                                                {match.advisory.severity
                                                                                    .charAt(
                                                                                        0,
                                                                                    )
                                                                                    .toUpperCase() +
                                                                                    match.advisory.severity.slice(
                                                                                        1,
                                                                                    )}
                                                                            </Badge>
                                                                            {match
                                                                                .advisory
                                                                                .cve && (
                                                                                <Badge variant="outline">
                                                                                    {
                                                                                        match
                                                                                            .advisory
                                                                                            .cve
                                                                                    }
                                                                                </Badge>
                                                                            )}
                                                                            {match.matchType ===
                                                                                'dependency' && (
                                                                                <Badge variant="secondary">
                                                                                    via{' '}
                                                                                    {
                                                                                        match.dependencyName
                                                                                    }
                                                                                </Badge>
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        ),
                                                    )}
                                                </div>
                                            </div>
                                        </>
                                    )}

                                {canManageVersions && (
                                    <>
                                        <Separator />
                                        <Dialog>
                                            <DialogTrigger asChild>
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                >
                                                    <Trash2 className="mr-1.5 h-3.5 w-3.5" />
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
                                    </>
                                )}
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
