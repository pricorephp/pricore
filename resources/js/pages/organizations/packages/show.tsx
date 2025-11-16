import { show } from '@/actions/App/Domains/Repository/Http/Controllers/RepositoryController';
import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Check, Copy, GitBranch, Globe, Lock } from 'lucide-react';
import { DateTime } from 'luxon';
import { useState } from 'react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;
type PackageData = App.Domains.Package.Contracts.Data.PackageData;
type PackageVersionData = App.Domains.Package.Contracts.Data.PackageVersionData;

interface PackageShowProps {
    organization: OrganizationData;
    package: PackageData;
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
    composerRepositoryUrl: string;
}

function CopyButton({ text }: { text: string }) {
    const [copied, setCopied] = useState(false);

    const copyToClipboard = async () => {
        if (!navigator?.clipboard) {
            // Fallback for browsers that don't support clipboard API
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
        <div className="relative">
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="h-6 w-6"
                onClick={copyToClipboard}
            >
                {copied ? (
                    <Check className="h-3 w-3 text-green-600 dark:text-green-400" />
                ) : (
                    <Copy className="h-3 w-3" />
                )}
            </Button>
            {copied && (
                <div className="absolute -top-9 left-1/2 z-50 -translate-x-1/2 rounded-md bg-green-600 px-2 py-1 text-xs font-medium whitespace-nowrap text-white shadow-lg dark:bg-green-500">
                    Copied!
                    <div className="absolute -bottom-1 left-1/2 h-2 w-2 -translate-x-1/2 rotate-45 bg-green-600 dark:bg-green-500" />
                </div>
            )}
        </div>
    );
}

function isStableVersion(version: PackageVersionData): boolean {
    return (
        !version.version.includes('dev') && /^\d+\.\d+/.test(version.version)
    );
}

function isDevVersion(version: PackageVersionData): boolean {
    return (
        version.version.includes('dev') ||
        version.normalizedVersion.startsWith('dev-')
    );
}

export default function PackageShow({
    organization,
    package: pkg,
    versions,
    composerRepositoryUrl,
}: PackageShowProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: organization.name,
            href: `/organizations/${organization.slug}`,
        },
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
                        <div className="space-y-2">
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
                            <div className="flex items-center gap-4 text-sm text-muted-foreground">
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
                                                {pkg.repositoryIdentifier}
                                            </Link>
                                        ) : (
                                            <span className="flex items-center gap-1 font-medium">
                                                <GitBranch className="h-3.5 w-3.5" />
                                                {pkg.repositoryIdentifier}
                                            </span>
                                        )}
                                    </span>
                                )}
                                <span>
                                    {versions.total} version
                                    {versions.total === 1 ? '' : 's'}
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
                        <p className="text-sm text-muted-foreground">
                            Add this repository to your{' '}
                            <code className="rounded bg-muted px-1 py-0.5 text-xs">
                                composer.json
                            </code>{' '}
                            to install packages from this organization:
                        </p>
                        <div className="relative">
                            <pre className="overflow-x-auto rounded bg-muted p-4 text-xs">
                                <code>{composerConfig}</code>
                            </pre>
                            <div className="absolute top-2 right-2">
                                <CopyButton text={composerConfig} />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="space-y-4">
                    <HeadingSmall
                        title="Versions"
                        description={`${versions.total} version${versions.total === 1 ? '' : 's'} available`}
                    />
                    {versions.data.length === 0 ? (
                        <Card>
                            <CardContent className="py-8 text-center text-sm text-muted-foreground">
                                No versions available yet.
                            </CardContent>
                        </Card>
                    ) : (
                        <>
                            <Card>
                                <Table>
                                    <TableHeader>
                                        <TableRow className="hover:bg-transparent">
                                            <TableHead>Version</TableHead>
                                            <TableHead>Released</TableHead>
                                            <TableHead>
                                                Install Command
                                            </TableHead>
                                            <TableHead>Source</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {versions.data.map((version) => {
                                            const installCommand = `composer require ${pkg.name}:${version.version}`;

                                            return (
                                                <TableRow key={version.uuid}>
                                                    <TableCell>
                                                        <div className="flex items-center gap-2">
                                                            <code className="font-mono text-sm">
                                                                {
                                                                    version.version
                                                                }
                                                            </code>
                                                            {isStableVersion(
                                                                version,
                                                            ) ? (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="text-xs"
                                                                >
                                                                    Stable
                                                                </Badge>
                                                            ) : isDevVersion(
                                                                  version,
                                                              ) ? (
                                                                <Badge
                                                                    variant="secondary"
                                                                    className="text-xs"
                                                                >
                                                                    Dev
                                                                </Badge>
                                                            ) : null}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        {version.releasedAt ? (
                                                            <div className="text-sm">
                                                                {DateTime.fromISO(
                                                                    version.releasedAt,
                                                                ).toRelative()}
                                                                <div className="text-xs text-muted-foreground">
                                                                    {DateTime.fromISO(
                                                                        version.releasedAt,
                                                                    ).toLocaleString(
                                                                        DateTime.DATETIME_SHORT,
                                                                    )}
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            <span className="text-sm text-muted-foreground">
                                                                —
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center gap-2">
                                                            <input
                                                                type="text"
                                                                readOnly
                                                                value={
                                                                    installCommand
                                                                }
                                                                className="w-80 rounded border border-input bg-background px-3 py-1.5 font-mono text-xs"
                                                                onClick={(e) =>
                                                                    (
                                                                        e.target as HTMLInputElement
                                                                    ).select()
                                                                }
                                                            />
                                                            <CopyButton
                                                                text={
                                                                    installCommand
                                                                }
                                                            />
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        {version.commitUrl ? (
                                                            <a
                                                                href={
                                                                    version.commitUrl
                                                                }
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="inline-flex items-center gap-1 rounded bg-muted px-2 py-1 font-mono text-xs transition-colors hover:bg-muted/80"
                                                            >
                                                                {version.sourceReference?.substring(
                                                                    0,
                                                                    7,
                                                                )}
                                                                <svg
                                                                    className="h-3 w-3"
                                                                    fill="none"
                                                                    stroke="currentColor"
                                                                    viewBox="0 0 24 24"
                                                                >
                                                                    <path
                                                                        strokeLinecap="round"
                                                                        strokeLinejoin="round"
                                                                        strokeWidth={
                                                                            2
                                                                        }
                                                                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                                                    />
                                                                </svg>
                                                            </a>
                                                        ) : version.sourceReference ? (
                                                            <code className="rounded bg-muted px-2 py-1 font-mono text-xs">
                                                                {version.sourceReference.substring(
                                                                    0,
                                                                    7,
                                                                )}
                                                            </code>
                                                        ) : (
                                                            <span className="text-sm text-muted-foreground">
                                                                —
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </Card>

                            {versions.last_page > 1 && (
                                <div className="flex items-center justify-between">
                                    <div className="text-sm text-muted-foreground">
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
                                                        className="px-3 py-2 text-sm text-muted-foreground"
                                                    >
                                                        <span
                                                            dangerouslySetInnerHTML={{
                                                                __html: link.label,
                                                            }}
                                                        />
                                                    </span>
                                                );
                                            }

                                            return (
                                                <Link
                                                    key={index}
                                                    href={link.url}
                                                    preserveScroll
                                                    className={`rounded px-3 py-2 text-sm transition-colors ${
                                                        link.active
                                                            ? 'bg-primary text-primary-foreground'
                                                            : 'bg-white text-muted-foreground/110 hover:bg-muted/80'
                                                    }`}
                                                >
                                                    <span
                                                        dangerouslySetInnerHTML={{
                                                            __html: link.label,
                                                        }}
                                                    />
                                                </Link>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
