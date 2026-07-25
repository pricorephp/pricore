import { update } from '@/actions/App/Domains/Security/Http/Controllers/SecuritySettingsController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { withOrganizationSettingsLayout } from '@/layouts/organization-settings-layout';
import { router } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;

interface Props {
    organization: OrganizationData;
}

export default function Security({ organization }: Props) {
    const updateSettings = (overrides: Record<string, boolean>) => {
        router.patch(
            update.url(organization.slug),
            {
                security_audits_enabled: organization.securityAuditsEnabled,
                security_notifications_enabled:
                    organization.securityNotificationsEnabled,
                anonymous_access_enabled: organization.anonymousAccessEnabled,
                ...overrides,
            },
            { preserveScroll: true },
        );
    };

    return (
        <div className="space-y-6">
            <div>
                <h3 className="text-lg font-medium">Security Settings</h3>
                <p className="text-muted-foreground">
                    Configure security auditing, notifications, and registry
                    access for your organization
                </p>
            </div>

            <div className="space-y-4 rounded-lg border bg-card p-6">
                <div className="flex items-center justify-between gap-4">
                    <div className="space-y-0.5">
                        <Label htmlFor="security-audits">Security audits</Label>
                        <p className="text-sm text-muted-foreground">
                            Automatically scan packages for known
                            vulnerabilities after syncing
                        </p>
                    </div>
                    <Switch
                        id="security-audits"
                        checked={organization.securityAuditsEnabled}
                        onCheckedChange={(checked) =>
                            updateSettings({ security_audits_enabled: checked })
                        }
                    />
                </div>

                <div className="flex items-center justify-between gap-4 border-t pt-4">
                    <div className="space-y-0.5">
                        <Label htmlFor="security-notifications">
                            Security notifications
                        </Label>
                        <p className="text-sm text-muted-foreground">
                            Email organization admins when new vulnerabilities
                            are detected
                        </p>
                    </div>
                    <Switch
                        id="security-notifications"
                        checked={organization.securityNotificationsEnabled}
                        disabled={!organization.securityAuditsEnabled}
                        onCheckedChange={(checked) =>
                            updateSettings({
                                security_notifications_enabled: checked,
                            })
                        }
                    />
                </div>
            </div>

            <div className="space-y-4 rounded-lg border bg-card p-6">
                <div className="flex items-center justify-between gap-4">
                    <div className="space-y-0.5">
                        <Label htmlFor="anonymous-access">
                            Anonymous access
                        </Label>
                        <p className="text-sm text-muted-foreground">
                            Allow pulling packages without a Composer
                            authentication token
                        </p>
                    </div>
                    <Switch
                        id="anonymous-access"
                        checked={organization.anonymousAccessEnabled}
                        onCheckedChange={(checked) =>
                            updateSettings({
                                anonymous_access_enabled: checked,
                            })
                        }
                    />
                </div>

                {organization.anonymousAccessEnabled && (
                    <Alert variant="destructive">
                        <TriangleAlert />
                        <AlertTitle>
                            All packages are publicly accessible
                        </AlertTitle>
                        <AlertDescription>
                            Anyone who can reach this registry can pull every
                            package in this organization without authentication.
                            Only enable this if access is restricted by other
                            means.
                        </AlertDescription>
                    </Alert>
                )}
            </div>
        </div>
    );
}

Security.layout = withOrganizationSettingsLayout;
