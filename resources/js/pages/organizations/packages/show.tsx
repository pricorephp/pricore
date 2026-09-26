import { show } from '@/actions/App/Domains/Repository/Http/Controllers/RepositoryController';
import { CopyButton } from '@/components/copy-button';
import { EmptyState } from '@/components/empty-state';
import GitProviderIcon, {
    getProviderColor,
} from '@/components/git-provider-icon';
import { VersionDownloadChart } from '@/components/stats/version-download-chart';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { Separator } from '@/components/ui/separator';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useDebounce } from '@/hooks/use-debounce';
import AppLayout from '@/layouts/app-layout';
import { createOrganizationBreadcrumb } from '@/lib/breadcrumbs';
import { cn, formatBytes } from '@/lib/utils';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Boxes,
    Calendar,
    ChartLine,
    Check,
    ChevronRight,
    Copy,
    EllipsisVertical,
    ExternalLink,
    GitBranch,
    GitCommit,
    Globe,
    Info,
    Link2,
    Lock,
    Package as PackageIcon,
    Search,
    ShieldAlert,
    Tag,
    Terminal,
    Trash2,
    TrendingDown,
    TrendingUp,
    Users,
    X,
} from 'lucide-react';
import { DateTime } from 'luxon';
import { useEffect, useRef, useState } from 'react';

type PackageTab = 'overview' | 'stats' | 'versions';

const PACKAGE_TABS: ReadonlyArray<{
    value: PackageTab;
    label: string;
    icon: typeof Info;
}> = [
    { value: 'overview', label: 'Readme', icon: BookOpen },
    { value: 'versions', label: 'Versions', icon: Tag },
    { value: 'stats', label: 'Downloads', icon: ChartLine },
];

function parseTabFromUrl(search: string): PackageTab {
    const value = new URLSearchParams(search).get('tab');

    if (value === 'versions' || value === 'stats') {
        return value;
    }

    return 'overview';
}

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
    primaryVersion: PackageVersionDetailData | null;
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
                    className="inline-flex shrink-0 items-center gap-1.5 rounded-md border bg-card px-2 py-1 text-xs text-muted-foreground opacity-0 transition-opacity group-hover/version:opacity-100 hover:bg-muted hover:text-foreground focus-visible:opacity-100"
                >
                    {copied ? (
                        <Check className="size-3 text-emerald-600 dark:text-emerald-400" />
                    ) : (
                        <Terminal className="size-3" />
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

function PackageSource({
    package: pkg,
    organizationSlug,
}: {
    package: PackageData;
    organizationSlug: string;
}) {
    return (
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-muted-foreground">
            {pkg.repositoryIdentifier && (
                <>
                    {pkg.repositoryUuid ? (
                        <Link
                            href={show.url([
                                organizationSlug,
                                pkg.repositoryUuid,
                            ])}
                            className="inline-flex items-center gap-1.5 transition-colors hover:text-foreground"
                        >
                            <GitProviderIcon
                                provider={pkg.repositoryProvider ?? 'git'}
                                className={cn(
                                    'size-4',
                                    getProviderColor(
                                        pkg.repositoryProvider ?? 'git',
                                    ),
                                )}
                            />
                            {pkg.repositoryIdentifier}
                        </Link>
                    ) : (
                        <span className="inline-flex items-center gap-1.5">
                            <GitProviderIcon
                                provider={pkg.repositoryProvider ?? 'git'}
                                className={cn(
                                    'size-4',
                                    getProviderColor(
                                        pkg.repositoryProvider ?? 'git',
                                    ),
                                )}
                            />
                            {pkg.repositoryIdentifier}
                        </span>
                    )}
                    {pkg.repositorySyncStatus && (
                        <Badge
                            variant={
                                pkg.repositorySyncStatus === 'ok'
                                    ? 'success'
                                    : pkg.repositorySyncStatus === 'failed'
                                      ? 'destructive'
                                      : 'secondary'
                            }
                        >
                            {pkg.repositorySyncStatus === 'ok'
                                ? 'Synced'
                                : pkg.repositorySyncStatus === 'failed'
                                  ? 'Sync failed'
                                  : 'Pending'}
                        </Badge>
                    )}
                    {pkg.repositoryLastSyncedAt && (
                        <span>
                            {DateTime.fromISO(
                                pkg.repositoryLastSyncedAt,
                            ).toRelative()}
                        </span>
                    )}
                </>
            )}
            {pkg.mirrorName && (
                <Link
                    href={`/organizations/${organizationSlug}/settings/mirrors`}
                    className="inline-flex items-center gap-1.5 transition-colors hover:text-foreground"
                >
                    <Copy className="size-3.5" />
                    Mirrored from {pkg.mirrorName}
                </Link>
            )}
            <span className="inline-flex items-center gap-1">
                {pkg.visibility === 'private' ? (
                    <Lock className="size-3.5" />
                ) : (
                    <Globe className="size-3.5" />
                )}
                {pkg.visibility === 'private' ? 'Private' : 'Public'}
            </span>
        </div>
    );
}

function DownloadSparkline({
    dailyDownloads,
}: {
    dailyDownloads: App.Domains.Organization.Contracts.Data.DailyDownloadData[];
}) {
    const width = 140;
    const height = 32;
    const values = dailyDownloads.map((day) => day.downloads);
    const max = Math.max(...values, 0);

    if (values.length < 2 || max === 0) {
        return (
            <svg
                width={width}
                height={height}
                className="mt-1 text-border md:ml-auto"
                aria-hidden
            >
                <line
                    x1={0}
                    x2={width}
                    y1={height - 2}
                    y2={height - 2}
                    stroke="currentColor"
                    strokeWidth={1.5}
                    strokeDasharray="3 4"
                />
            </svg>
        );
    }

    const points = values
        .map((value, index) => {
            const x = (index / (values.length - 1)) * width;
            const y = height - 2 - (value / max) * (height - 4);

            return `${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');

    return (
        <svg
            width={width}
            height={height}
            className="mt-1 text-primary md:ml-auto"
            aria-label="Downloads over the last 30 days"
        >
            <polyline
                points={points}
                fill="none"
                stroke="currentColor"
                strokeWidth={1.75}
                strokeLinejoin="round"
                strokeLinecap="round"
            />
        </svg>
    );
}

function InstallCommand({ packageName }: { packageName: string }) {
    const command = `composer require ${packageName}`;

    return (
        <div className="flex items-center gap-3 rounded-lg border bg-surface-inset py-2 pr-2 pl-4 font-mono text-base">
            <span className="text-muted-foreground select-none">$</span>
            <code className="min-w-0 flex-1 truncate">{command}</code>
            <CopyButton text={command} tooltip="Copy install command" />
        </div>
    );
}

function DependencyList({
    title,
    entries,
}: {
    title: string;
    entries: PackageVersionDetailData['require'];
}) {
    const map = entries as unknown as Record<string, string> | null;

    if (!map || Object.keys(map).length === 0) {
        return null;
    }

    return (
        <div>
            <div className="mb-2 text-sm font-medium text-muted-foreground">
                {title}
            </div>
            <div className="divide-y rounded-lg border bg-muted/30">
                {Object.entries(map).map(([name, constraint]) => (
                    <div
                        key={name}
                        className="flex items-baseline justify-between gap-3 px-3 py-2 text-sm"
                    >
                        <code className="min-w-0 truncate font-mono text-foreground">
                            {name}
                        </code>
                        <code className="shrink-0 text-right font-mono text-xs text-muted-foreground">
                            {constraint}
                        </code>
                    </div>
                ))}
            </div>
        </div>
    );
}

function PackageDetails({ version }: { version: PackageVersionDetailData }) {
    const phpRequirement = (
        version.require as unknown as Record<string, string> | null
    )?.php;

    const authors =
        (version.authors as unknown as Array<{
            name?: string;
            email?: string;
            homepage?: string;
        }> | null) ?? [];
    const keywords = (version.keywords as unknown as string[] | null) ?? [];

    const facts: Array<{ label: string; value: string; mono?: boolean }> = [
        { label: 'Latest version', value: version.version, mono: true },
    ];

    if (version.type) {
        facts.push({ label: 'Type', value: version.type });
    }

    if (version.license) {
        facts.push({ label: 'License', value: version.license });
    }

    if (phpRequirement) {
        facts.push({ label: 'PHP', value: phpRequirement, mono: true });
    }

    return (
        <div className="divide-y">
            <section className="pb-5">
                <h3 className="mb-3 font-semibold">About</h3>
                <dl className="space-y-2.5">
                    {facts.map((fact) => (
                        <div
                            key={fact.label}
                            className="flex items-baseline justify-between gap-4"
                        >
                            <dt className="shrink-0 text-muted-foreground">
                                {fact.label}
                            </dt>
                            <dd
                                className={cn(
                                    'min-w-0 truncate text-right font-medium',
                                    fact.mono && 'font-mono',
                                )}
                                title={fact.value}
                            >
                                {fact.value}
                            </dd>
                        </div>
                    ))}
                </dl>
            </section>

            {authors.length > 0 && (
                <section className="py-5">
                    <h3 className="mb-3 font-semibold">
                        Author{authors.length === 1 ? '' : 's'}
                    </h3>
                    <ul className="space-y-2.5">
                        {authors.map((author, index) => {
                            const name =
                                author.name ?? author.email ?? 'Unknown';

                            return (
                                <li
                                    key={`${name}-${index}`}
                                    className="flex items-center gap-2.5"
                                >
                                    <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium text-muted-foreground">
                                        {name
                                            .split(/\s+/)
                                            .map((part) => part.charAt(0))
                                            .slice(0, 2)
                                            .join('')
                                            .toUpperCase()}
                                    </span>
                                    <div className="min-w-0">
                                        <div className="truncate font-medium">
                                            {name}
                                        </div>
                                        {author.email && author.name && (
                                            <div className="truncate text-xs text-muted-foreground">
                                                {author.email}
                                            </div>
                                        )}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </section>
            )}

            {keywords.length > 0 && (
                <section className="pt-5">
                    <h3 className="mb-3 font-semibold">Keywords</h3>
                    <div className="flex flex-wrap gap-1.5">
                        {keywords.map((keyword) => (
                            <Badge key={keyword} variant="outline">
                                {keyword}
                            </Badge>
                        ))}
                    </div>
                </section>
            )}
        </div>
    );
}

function ReadmeSection({ version }: { version: PackageVersionDetailData }) {
    if (!version.readmeHtml) {
        return null;
    }

    return (
        <Card>
            <CardContent>
                <div
                    className="prose max-w-none break-words dark:prose-invert prose-code:text-[0.923em] prose-pre:text-xs prose-table:text-sm [&_pre_code]:text-[length:inherit]"
                    dangerouslySetInnerHTML={{ __html: version.readmeHtml }}
                />
            </CardContent>
        </Card>
    );
}

function DownloadMetric({
    label,
    value,
    children,
}: {
    label: string;
    value: string;
    children?: React.ReactNode;
}) {
    return (
        <div className="px-5 py-4">
            <div className="text-muted-foreground">{label}</div>
            <div className="mt-1 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span className="text-2xl font-semibold tracking-tight tabular-nums">
                    {value}
                </span>
                {children}
            </div>
        </div>
    );
}

function StatsTabContent({
    downloadStats,
}: {
    downloadStats: PackageDownloadStatsData;
}) {
    const currentPeriod = downloadStats.currentPeriodDownloads;
    const previousPeriod = downloadStats.previousPeriodDownloads;

    let trendLabel: string;
    if (previousPeriod === 0) {
        trendLabel = currentPeriod === 0 ? 'No prior data' : 'New activity';
    } else {
        const change =
            ((currentPeriod - previousPeriod) / previousPeriod) * 100;
        const sign = change > 0 ? '+' : '';
        trendLabel = `${sign}${change.toFixed(1)}% vs prior 30 days`;
    }

    const TrendIcon =
        previousPeriod > 0 && currentPeriod < previousPeriod
            ? TrendingDown
            : TrendingUp;

    const dailyAverage = currentPeriod / 30;

    return (
        <div className="space-y-6">
            <div className="grid grid-cols-1 divide-y rounded-lg border bg-card sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <DownloadMetric
                    label="Total downloads"
                    value={downloadStats.totalDownloads.toLocaleString()}
                />
                <DownloadMetric
                    label="Last 30 days"
                    value={currentPeriod.toLocaleString()}
                >
                    <span
                        className={cn(
                            'inline-flex items-center gap-1 text-xs',
                            TrendIcon === TrendingDown
                                ? 'text-red-600 dark:text-red-400'
                                : previousPeriod > 0
                                  ? 'text-emerald-600 dark:text-emerald-400'
                                  : 'text-muted-foreground',
                        )}
                    >
                        <TrendIcon className="size-3.5" />
                        {trendLabel}
                    </span>
                </DownloadMetric>
                <DownloadMetric
                    label="Daily average"
                    value={dailyAverage.toLocaleString(undefined, {
                        maximumFractionDigits: 1,
                    })}
                >
                    <span className="text-xs text-muted-foreground">
                        per day, last 30 days
                    </span>
                </DownloadMetric>
            </div>

            <VersionDownloadChart
                title="Downloads (Last 30 Days)"
                versionData={downloadStats.versionDailyDownloads}
                fallbackData={downloadStats.dailyDownloads}
            />
        </div>
    );
}

interface VersionsTabContentProps {
    versions: PackageShowProps['versions'];
    queryFilter: string;
    typeFilter: string;
    hasActiveFilters: boolean;
    onQueryChange: (value: string) => void;
    onTypeChange: (value: string) => void;
    onClear: () => void;
    onOpenVersion: (uuid: string) => void;
    onPageChange: (page: number) => void;
    packageName: string;
    latestVersion: string | null;
}

function isBranchVersion(version: string): boolean {
    return version.startsWith('dev-') || version.endsWith('-dev');
}

function formatReleaseDate(releasedAt: string): string {
    const date = DateTime.fromISO(releasedAt);

    return DateTime.now().diff(date, 'days').days < 7
        ? (date.toRelative() ?? '')
        : date.toLocaleString(DateTime.DATE_MED);
}

const VERSION_TYPE_OPTIONS = [
    { value: 'all', label: 'All' },
    { value: 'stable', label: 'Stable' },
    { value: 'dev', label: 'Dev' },
] as const;

const severityTextClasses: Record<string, string> = {
    critical: 'text-red-600 dark:text-red-400',
    high: 'text-orange-600 dark:text-orange-400',
    medium: 'text-amber-600 dark:text-amber-400',
    low: 'text-blue-600 dark:text-blue-400',
};

function VersionRow({
    version,
    isLatest,
    packageName,
    onOpen,
}: {
    version: PackageVersionData;
    isLatest: boolean;
    packageName: string;
    onOpen: () => void;
}) {
    const isBranch = isBranchVersion(version.version);

    return (
        <div
            role="button"
            tabIndex={0}
            onClick={onOpen}
            onKeyDown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    onOpen();
                }
            }}
            className="group/version flex cursor-pointer items-center gap-4 px-4 py-2.5 transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none"
        >
            <div className="flex min-w-0 flex-1 items-center gap-2">
                {isBranch ? (
                    <GitBranch className="size-3.5 shrink-0 text-muted-foreground" />
                ) : (
                    <Tag
                        className={cn(
                            'size-3.5 shrink-0',
                            isLatest ? 'text-primary' : 'text-muted-foreground',
                        )}
                    />
                )}
                <span
                    className={cn(
                        'truncate font-mono',
                        isLatest ? 'font-semibold' : 'font-medium',
                    )}
                >
                    {version.version}
                </span>
                {isLatest && <Badge>Latest</Badge>}
                <span className="ml-auto pl-2">
                    <CopyInstallButton
                        text={`composer require ${packageName}:${version.version}`}
                    />
                </span>
            </div>
            <div className="hidden w-28 md:block">
                {version.vulnerabilityCount > 0 ? (
                    <span
                        className={cn(
                            'inline-flex items-center gap-1.5 font-medium',
                            severityTextClasses[
                                version.highestSeverity ?? 'low'
                            ],
                        )}
                    >
                        <ShieldAlert className="size-3.5" />
                        {version.vulnerabilityCount}{' '}
                        {version.highestSeverity ?? 'issue'}
                    </span>
                ) : (
                    <span className="text-muted-foreground/50">&mdash;</span>
                )}
            </div>
            <div className="hidden w-16 text-right text-muted-foreground tabular-nums md:block">
                {version.distSize !== null ? (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span>{formatBytes(version.distSize)}</span>
                        </TooltipTrigger>
                        <TooltipContent>
                            Archive stored and served by Pricore
                        </TooltipContent>
                    </Tooltip>
                ) : (
                    <span className="text-muted-foreground/50">&mdash;</span>
                )}
            </div>
            <div className="hidden w-20 lg:block">
                {version.sourceReference &&
                    (version.commitUrl ? (
                        <a
                            href={version.commitUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            onClick={(event) => event.stopPropagation()}
                            className="font-mono text-muted-foreground hover:text-foreground hover:underline"
                        >
                            {version.sourceReference.substring(0, 7)}
                        </a>
                    ) : (
                        <span className="font-mono text-muted-foreground">
                            {version.sourceReference.substring(0, 7)}
                        </span>
                    ))}
            </div>
            <div className="hidden w-28 text-right text-muted-foreground sm:block">
                {version.releasedAt && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span>{formatReleaseDate(version.releasedAt)}</span>
                        </TooltipTrigger>
                        <TooltipContent>
                            {DateTime.fromISO(
                                version.releasedAt,
                            ).toLocaleString(DateTime.DATETIME_MED)}
                        </TooltipContent>
                    </Tooltip>
                )}
            </div>
            <ChevronRight className="size-4 shrink-0 text-muted-foreground/50 transition-transform group-hover/version:translate-x-0.5 group-hover/version:text-muted-foreground" />
        </div>
    );
}

function VersionsTabContent({
    versions,
    queryFilter,
    typeFilter,
    hasActiveFilters,
    onQueryChange,
    onTypeChange,
    onClear,
    onOpenVersion,
    onPageChange,
    packageName,
    latestVersion,
}: VersionsTabContentProps) {
    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-3">
                <div className="relative w-full max-w-xs">
                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder="Version or commit hash"
                        value={queryFilter}
                        onChange={(e) => onQueryChange(e.target.value)}
                        className="h-9 pl-9"
                    />
                </div>
                <div
                    role="radiogroup"
                    aria-label="Version type"
                    className="inline-flex rounded-lg border bg-surface-inset p-0.5"
                >
                    {VERSION_TYPE_OPTIONS.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            role="radio"
                            aria-checked={typeFilter === option.value}
                            onClick={() => onTypeChange(option.value)}
                            className={cn(
                                'rounded-md px-3 py-1 font-medium transition-colors',
                                typeFilter === option.value
                                    ? 'bg-card text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>
                {hasActiveFilters && (
                    <Button variant="ghost" size="sm" onClick={onClear}>
                        <X className="size-4" />
                        Clear
                    </Button>
                )}
                <span className="ml-auto text-muted-foreground tabular-nums">
                    {versions.total}{' '}
                    {versions.total === 1 ? 'version' : 'versions'}
                </span>
            </div>

            {versions.data.length === 0 ? (
                <EmptyState
                    icon={Tag}
                    title={
                        hasActiveFilters
                            ? 'No matching versions'
                            : 'No versions yet'
                    }
                    description={
                        hasActiveFilters
                            ? 'Try a different version, commit hash or type.'
                            : 'Versions appear here once the repository has been synced.'
                    }
                    className="py-12"
                />
            ) : (
                <>
                    <div className="overflow-hidden rounded-lg border bg-card">
                        <div className="flex items-center gap-4 border-b bg-surface-inset px-4 py-2 text-xs font-medium text-muted-foreground">
                            <span className="flex-1">Version</span>
                            <span className="hidden w-28 md:block">
                                Advisories
                            </span>
                            <span className="hidden w-16 text-right md:block">
                                Size
                            </span>
                            <span className="hidden w-20 lg:block">Commit</span>
                            <span className="hidden w-28 text-right sm:block">
                                Released
                            </span>
                            <span className="w-4" />
                        </div>
                        <div className="divide-y">
                            {versions.data.map((version) => (
                                <VersionRow
                                    key={version.uuid}
                                    version={version}
                                    isLatest={version.version === latestVersion}
                                    packageName={packageName}
                                    onOpen={() => onOpenVersion(version.uuid)}
                                />
                            ))}
                        </div>
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
                                    versions.current_page * versions.per_page,
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
                                                onPageChange(
                                                    pageNumber
                                                        ? Number(pageNumber)
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
    primaryVersion,
}: PackageShowProps) {
    const { auth } = usePage<{
        auth: { organizations: OrganizationData[] };
    }>().props;

    const [queryFilter, setQueryFilter] = useState(filters.query);
    const [typeFilter, setTypeFilter] = useState(filters.type || 'all');
    const [page, setPage] = useState(versions.current_page);
    const [activeTab, setActiveTab] = useState<PackageTab>(() =>
        typeof window === 'undefined'
            ? 'overview'
            : parseTabFromUrl(window.location.search),
    );

    const debouncedQuery = useDebounce(queryFilter, 300);

    const isInitialMount = useRef(true);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const params = new URLSearchParams(window.location.search);

        if (activeTab === 'overview') {
            params.delete('tab');
        } else {
            params.set('tab', activeTab);
        }

        const query = params.toString();
        const next = `${window.location.pathname}${query ? `?${query}` : ''}`;

        if (next !== window.location.pathname + window.location.search) {
            window.history.replaceState(null, '', next);
        }
    }, [activeTab]);

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
        if (activeTab !== 'overview') {
            params.tab = activeTab;
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
        // activeTab is intentionally omitted: switching tabs is a client-only
        // action and should not trigger an Inertia request. We include it in
        // the params above so the URL stays in sync when a filter changes
        // while the user is on a non-default tab.
        // eslint-disable-next-line react-hooks/exhaustive-deps
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${pkg.name} - ${organization.name}`} />

            <div className="mx-auto w-7xl space-y-6 p-6">
                <header className="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
                    <div className="flex min-w-0 gap-4">
                        <div className="flex size-14 shrink-0 items-center justify-center rounded-xl border bg-surface-inset">
                            <PackageIcon
                                className="size-7 text-muted-foreground"
                                strokeWidth={1.5}
                            />
                        </div>
                        <div className="min-w-0 space-y-1.5">
                            <div className="flex flex-wrap items-center gap-2.5">
                                <h1 className="truncate text-3xl font-semibold tracking-tight">
                                    {pkg.name}
                                </h1>
                                {pkg.latestVersion && (
                                    <span className="rounded-md border bg-surface-inset px-2 py-0.5 font-mono">
                                        {pkg.latestVersion}
                                    </span>
                                )}
                            </div>
                            {(pkg.description ??
                                primaryVersion?.description) && (
                                <p className="text-base text-muted-foreground">
                                    {pkg.description ??
                                        primaryVersion?.description}
                                </p>
                            )}
                            <PackageSource
                                package={pkg}
                                organizationSlug={organization.slug}
                            />
                        </div>
                    </div>
                    <div className="flex shrink-0 items-start gap-2">
                        <button
                            type="button"
                            onClick={() => setActiveTab('stats')}
                            className="group/downloads text-left md:text-right"
                        >
                            <div className="text-muted-foreground transition-colors group-hover/downloads:text-foreground">
                                Downloads
                            </div>
                            <div className="text-3xl font-semibold tracking-tight tabular-nums">
                                {downloadStats.totalDownloads.toLocaleString()}
                            </div>
                            <DownloadSparkline
                                dailyDownloads={downloadStats.dailyDownloads}
                            />
                        </button>
                        {canDeletePackage && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Package actions"
                                    >
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
                </header>

                <InstallCommand packageName={pkg.name} />

                <div className="space-y-6">
                    <nav className="flex gap-6 border-b">
                        {PACKAGE_TABS.map((tab) => {
                            const isActive = activeTab === tab.value;

                            return (
                                <button
                                    key={tab.value}
                                    type="button"
                                    onClick={() => setActiveTab(tab.value)}
                                    className={cn(
                                        '-mb-px flex shrink-0 items-center gap-2 border-b-2 pb-3 text-base font-medium transition-colors',
                                        isActive
                                            ? 'border-foreground text-foreground'
                                            : 'border-transparent text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    <tab.icon className="size-4" />
                                    {tab.label}
                                    {tab.value === 'versions' && (
                                        <span className="rounded-full bg-muted px-1.5 text-xs font-medium text-muted-foreground tabular-nums">
                                            {versions.total}
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </nav>

                    <div className="w-full min-w-0 flex-1 space-y-6">
                        {activeTab === 'overview' && (
                            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:gap-10">
                                <div className="min-w-0">
                                    {primaryVersion?.readmeHtml ? (
                                        <ReadmeSection
                                            version={primaryVersion}
                                        />
                                    ) : (
                                        <EmptyState
                                            icon={BookOpen}
                                            title="No README"
                                            description="Add a README.md to the repository and it will show up here after the next sync."
                                        />
                                    )}
                                </div>
                                {primaryVersion && (
                                    <aside className="lg:sticky lg:top-6 lg:self-start">
                                        <PackageDetails
                                            version={primaryVersion}
                                        />
                                    </aside>
                                )}
                            </div>
                        )}

                        {activeTab === 'stats' && (
                            <StatsTabContent downloadStats={downloadStats} />
                        )}

                        {activeTab === 'versions' && (
                            <VersionsTabContent
                                versions={versions}
                                queryFilter={queryFilter}
                                typeFilter={typeFilter}
                                hasActiveFilters={hasActiveFilters}
                                onQueryChange={handleQueryChange}
                                onTypeChange={handleTypeChange}
                                onClear={clearFilters}
                                onOpenVersion={openVersion}
                                onPageChange={setPage}
                                packageName={pkg.name}
                                latestVersion={pkg.latestVersion}
                            />
                        )}
                    </div>
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
                                                href={activeVersion.tagUrl}
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

                                {(activeVersion.require ||
                                    activeVersion.requireDev ||
                                    activeVersion.conflict ||
                                    activeVersion.provide ||
                                    activeVersion.replace ||
                                    activeVersion.suggest) && (
                                    <>
                                        <Separator />
                                        <div className="space-y-4">
                                            <div className="flex items-center gap-1.5 text-sm font-medium text-muted-foreground">
                                                <Boxes className="h-4 w-4" />
                                                Requirements
                                            </div>
                                            <DependencyList
                                                title="Requires"
                                                entries={activeVersion.require}
                                            />
                                            <DependencyList
                                                title="Requires (Dev)"
                                                entries={
                                                    activeVersion.requireDev
                                                }
                                            />
                                            <DependencyList
                                                title="Conflicts"
                                                entries={activeVersion.conflict}
                                            />
                                            <DependencyList
                                                title="Replaces"
                                                entries={activeVersion.replace}
                                            />
                                            <DependencyList
                                                title="Provides"
                                                entries={activeVersion.provide}
                                            />
                                            <DependencyList
                                                title="Suggests"
                                                entries={activeVersion.suggest}
                                            />
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
