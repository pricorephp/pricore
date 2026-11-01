import { destroy } from '@/actions/App/Domains/Mcp/Http/Controllers/ConnectedAppController';
import { CopyButton } from '@/components/copy-button';
import InfoBox from '@/components/info-box';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit as editProfile } from '@/routes/profile';
import { index as connectedAppsIndex } from '@/routes/settings/connected-apps';
import { type BreadcrumbItem } from '@/types';
import { Form, Head } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { DateTime } from 'luxon';
import { useState } from 'react';

type ConnectedAppData = App.Domains.Mcp.Contracts.Data.ConnectedAppData;

interface ConnectedAppsPageProps {
    connectedApps: ConnectedAppData[];
    mcpUrl: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Settings',
        href: editProfile().url,
    },
    {
        title: 'Connected Apps',
        href: connectedAppsIndex().url,
    },
];

function formatDate(value: string | null): string {
    return value
        ? DateTime.fromISO(value).toLocaleString(DateTime.DATE_MED)
        : 'Unknown';
}

export default function ConnectedApps({
    connectedApps,
    mcpUrl,
}: ConnectedAppsPageProps) {
    const [selectedApp, setSelectedApp] = useState<ConnectedAppData | null>(
        null,
    );
    const addCommand = `claude mcp add --transport http pricore ${mcpUrl}`;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Connected apps" />

            <SettingsLayout>
                <div className="space-y-6">
                    <div>
                        <h3 className="text-lg font-medium">Connected Apps</h3>
                        <p className="text-muted-foreground">
                            AI assistants and other MCP clients you have
                            authorized to use your account
                        </p>
                    </div>

                    <div className="rounded-lg border bg-card px-4 py-2">
                        {connectedApps.length === 0 ? (
                            <div className="my-2 rounded-lg border border-dashed p-8 text-center">
                                <p className="text-muted-foreground">
                                    No connected apps yet. Connect an MCP client
                                    to see it here.
                                </p>
                            </div>
                        ) : (
                            <div className="grid space-y-2">
                                {connectedApps.map((app, index) => (
                                    <div key={app.clientId}>
                                        {index > 0 && <Separator />}
                                        <div className="flex items-start justify-between py-3">
                                            <div className="flex-1 space-y-0.5">
                                                <h3 className="font-medium">
                                                    {app.name}
                                                </h3>
                                                <div className="flex flex-wrap gap-x-3 gap-y-0.5 text-sm text-muted-foreground">
                                                    <span>
                                                        Authorized:{' '}
                                                        {formatDate(
                                                            app.authorizedAt,
                                                        )}
                                                    </span>
                                                    <span>
                                                        Last active:{' '}
                                                        {app.lastActiveAt
                                                            ? DateTime.fromISO(
                                                                  app.lastActiveAt,
                                                              ).toRelative()
                                                            : 'Unknown'}
                                                    </span>
                                                </div>
                                            </div>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    setSelectedApp(app)
                                                }
                                                aria-label={`Revoke ${app.name}`}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <InfoBox
                        title="Connect an MCP client"
                        description="Add Pricore as a remote MCP server in Claude, Cursor or VS Code. You will be asked to sign in and approve access, after which the app acts with your role in each organization."
                    >
                        <div className="flex items-start gap-2">
                            <code className="min-w-0 flex-1 rounded border border-border bg-muted p-2 font-mono text-xs break-all">
                                {addCommand}
                            </code>
                            <CopyButton text={addCommand} />
                        </div>
                    </InfoBox>
                </div>

                <Dialog
                    open={selectedApp !== null}
                    onOpenChange={() => setSelectedApp(null)}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Revoke Access</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to revoke this
                                application's access? It will have to be
                                authorized again to reconnect.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="rounded-md border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950">
                            <p className="font-medium text-red-900 dark:text-red-100">
                                Application: {selectedApp?.name}
                            </p>
                            <p className="mt-1 text-red-700 dark:text-red-300">
                                Its access and refresh tokens stop working
                                immediately.
                            </p>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setSelectedApp(null)}
                            >
                                Cancel
                            </Button>
                            {selectedApp && (
                                <Form
                                    action={destroy.url(selectedApp.clientId)}
                                    method="delete"
                                    onSuccess={() => setSelectedApp(null)}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Revoking...'
                                                : 'Revoke Access'}
                                        </Button>
                                    )}
                                </Form>
                            )}
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </SettingsLayout>
        </AppLayout>
    );
}
