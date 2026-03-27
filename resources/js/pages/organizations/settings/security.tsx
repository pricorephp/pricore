import { update } from '@/actions/App/Domains/Security/Http/Controllers/SecuritySettingsController';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { withOrganizationSettingsLayout } from '@/layouts/organization-settings-layout';
import { router } from '@inertiajs/react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;

interface Props {
    organization: OrganizationData;
}

export default function Security({ organization }: Props) {
    const handleToggle = (
        field: string,
        value: boolean,
        otherFields: Record<string, boolean>,
    ) => {
        router.patch(
            update.url(organization.slug),
            { [field]: value, ...otherFields },
            { preserveScroll: true },
        );
    };

    return (
        <div className="space-y-6">
            <div>
                <h3 className="text-lg font-medium">Security Settings</h3>
                <p className="text-muted-foreground">
                    Configure security auditing and notifications for your
                    organization
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
                            handleToggle('security_audits_enabled', checked, {
                                security_notifications_enabled:
                                    organization.securityNotificationsEnabled,
                            })
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
                            handleToggle(
                                'security_notifications_enabled',
                                checked,
                                {
                                    security_audits_enabled:
                                        organization.securityAuditsEnabled,
                                },
                            )
                        }
                    />
                </div>
            </div>
        </div>
    );
}

Security.layout = withOrganizationSettingsLayout;
