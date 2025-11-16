import { update } from '@/actions/App/Domains/Organization/Http/Controllers/SettingsController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import OrganizationSettingsLayout from '@/layouts/organization-settings-layout';
import { Form } from '@inertiajs/react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;

interface Props {
    organization: OrganizationData;
}

export default function General({ organization }: Props) {
    return (
        <div className="space-y-6">
            <div>
                <h3 className="text-lg font-medium">General Settings</h3>
                <p className="text-sm text-muted-foreground">
                    Update your organization's basic information
                </p>
            </div>

            <div className="rounded-lg border bg-card p-6">
                <Form
                    action={update.url([organization.slug])}
                    method="patch"
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="space-y-2">
                                <Label htmlFor="name">Organization Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={organization.name}
                                    required
                                    maxLength={255}
                                />
                                {errors.name && (
                                    <p className="text-sm text-red-600 dark:text-red-400">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="slug">Organization Slug</Label>
                                <Input
                                    id="slug"
                                    name="slug"
                                    value={organization.slug}
                                    disabled
                                    className="bg-muted"
                                />
                                <p className="text-sm text-muted-foreground">
                                    The slug cannot be changed after creation
                                </p>
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : 'Save Changes'}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </div>
    );
}

General.layout = (page: React.ReactNode) => (
    <AppLayout>
        <OrganizationSettingsLayout>{page}</OrganizationSettingsLayout>
    </AppLayout>
);
