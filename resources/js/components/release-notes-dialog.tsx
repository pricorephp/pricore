import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { DateTime } from 'luxon';

type ReleaseData = App.Domains.Release.Contracts.Data.ReleaseData;
type ReleaseInfoData = Omit<
    App.Domains.Release.Contracts.Data.ReleaseInfoData,
    'releases'
> & {
    releases: ReleaseData[];
};

interface ReleaseNotesDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    info: ReleaseInfoData | null;
}

export default function ReleaseNotesDialog({
    open,
    onOpenChange,
    info,
}: ReleaseNotesDialogProps) {
    const releases = info?.releases ?? [];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[80vh] w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-2xl">
                <DialogHeader className="border-b border-border px-6 pt-5 pb-4">
                    <DialogTitle>Release notes</DialogTitle>
                    <DialogDescription>
                        {renderStatusLine(info)}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex-1 overflow-y-auto px-6 py-4">
                    {releases.length === 0 ? (
                        <p className="text-muted-foreground">
                            Couldn&apos;t load release notes.{' '}
                            <a
                                href="https://github.com/pricorephp/pricore/releases"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="underline hover:text-foreground"
                            >
                                View on GitHub
                            </a>
                            .
                        </p>
                    ) : (
                        <ol className="flex flex-col gap-8">
                            {releases.map((release) => (
                                <ReleaseEntry
                                    key={release.tagName}
                                    release={release}
                                    isCurrent={
                                        release.version ===
                                        info?.currentVersion
                                    }
                                />
                            ))}
                        </ol>
                    )}
                </div>

                <div className="border-t border-border px-6 py-3 text-right">
                    <a
                        href="https://github.com/pricorephp/pricore/releases"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-sm text-muted-foreground hover:text-foreground hover:underline"
                    >
                        View all releases on GitHub
                    </a>
                </div>
            </DialogContent>
        </Dialog>
    );
}

function renderStatusLine(info: ReleaseInfoData | null): string {
    if (!info) return 'Latest releases from GitHub.';

    const { currentVersion, latestVersion, isOutdated } = info;

    if (currentVersion && latestVersion) {
        if (isOutdated) {
            return `Running v${currentVersion} · Latest v${latestVersion}`;
        }
        return `Running v${currentVersion} · You're up to date`;
    }

    if (latestVersion) {
        return `Latest release: v${latestVersion}`;
    }

    return 'Latest releases from GitHub.';
}

interface ReleaseEntryProps {
    release: ReleaseData;
    isCurrent: boolean;
}

function ReleaseEntry({ release, isCurrent }: ReleaseEntryProps) {
    const publishedAt = release.publishedAt
        ? DateTime.fromISO(release.publishedAt).toLocaleString(
              DateTime.DATE_MED,
          )
        : null;

    return (
        <li>
            <div className="mb-2 flex items-baseline gap-2">
                <a
                    href={release.htmlUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="font-semibold text-foreground hover:underline"
                >
                    {release.name}
                </a>
                {publishedAt && (
                    <span className="text-xs text-muted-foreground">
                        {publishedAt}
                    </span>
                )}
                {isCurrent && (
                    <Badge variant="success" className="ml-auto">
                        Current
                    </Badge>
                )}
            </div>
            {release.bodyHtml ? (
                <div
                    className="text-muted-foreground [&_a]:text-foreground [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:border-border [&_blockquote]:pl-3 [&_blockquote]:italic [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_code]:py-0.5 [&_code]:text-foreground [&_h1]:mt-3 [&_h1]:mb-1 [&_h1]:text-base [&_h1]:font-semibold [&_h1]:text-foreground [&_h2]:mt-3 [&_h2]:mb-1 [&_h2]:text-base [&_h2]:font-semibold [&_h2]:text-foreground [&_h3]:mt-3 [&_h3]:mb-1 [&_h3]:text-base [&_h3]:font-semibold [&_h3]:text-foreground [&_li]:mb-1 [&_ol]:my-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:my-2 [&_pre]:my-2 [&_pre]:overflow-x-auto [&_pre]:rounded [&_pre]:bg-muted [&_pre]:p-2 [&_pre]:text-xs [&_pre_code]:bg-transparent [&_pre_code]:p-0 [&_ul]:my-2 [&_ul]:list-disc [&_ul]:pl-5"
                    dangerouslySetInnerHTML={{ __html: release.bodyHtml }}
                />
            ) : (
                <p className="text-muted-foreground italic">
                    No notes provided.
                </p>
            )}
        </li>
    );
}
