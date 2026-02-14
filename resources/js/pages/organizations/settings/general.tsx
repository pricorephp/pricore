import { destroy } from '@/actions/App/Domains/Organization/Http/Controllers/OrganizationController';
import { update } from '@/actions/App/Domains/Organization/Http/Controllers/SettingsController';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { Label } from '@/components/ui/label';
import { withOrganizationSettingsLayout } from '@/layouts/organization-settings-layout';
import { Form } from '@inertiajs/react';
import { useRef, useState } from 'react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;

interface Props {
    organization: OrganizationData;
    isOwner?: boolean;
}

export default function General({ organization, isOwner = false }: Props) {
    return (
        <div className="space-y-6">
            <div>
                <h3 className="text-lg font-medium">General Settings</h3>
                <p className="text-muted-foreground">
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
                            <div className="grid space-y-2">
                                <Label htmlFor="name">Organization Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={organization.name}
                                    required
                                    maxLength={255}
                                />
                                {errors.name && (
                                    <p className="text-red-600 dark:text-red-400">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="grid space-y-2">
                                <Label htmlFor="slug">Organization Slug</Label>
                                {isOwner ? (
                                    <>
                                        <Input
                                            id="slug"
                                            name="slug"
                                            defaultValue={organization.slug}
                                            required
                                            maxLength={255}
                                            pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                                            title="Slug must contain only lowercase letters, numbers, and hyphens"
                                        />
                                        <p className="text-muted-foreground">
                                            The slug is used in URLs. Only
                                            lowercase letters, numbers, and
                                            hyphens are allowed.
                                        </p>
                                        {errors.slug && (
                                            <p className="text-red-600 dark:text-red-400">
                                                {errors.slug}
                                            </p>
                                        )}
                                    </>
                                ) : (
                                    <>
                                        <Input
                                            id="slug"
                                            name="slug"
                                            value={organization.slug}
                                            disabled
                                            className="bg-muted"
                                        />
                                        <p className="text-muted-foreground">
                                            Only organization owners can change
                                            the slug
                                        </p>
                                    </>
                                )}
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

            {isOwner && (
                <DeleteOrganization organization={organization} />
            )}
        </div>
    );
}

function DeleteOrganization({
    organization,
}: {
    organization: OrganizationData;
}) {
    const [confirmationName, setConfirmationName] = useState('');
    const nameInput = useRef<HTMLInputElement>(null);

    return (
        <div className="space-y-6">
            <HeadingSmall
                title="Delete organization"
                description="Permanently delete this organization and all of its resources"
            />
            <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                <div className="relative space-y-0.5 text-red-600 dark:text-red-100">
                    <p className="font-medium">Warning</p>
                    <p>
                        This action cannot be undone. All packages,
                        repositories, tokens, and members will be removed.
                    </p>
                </div>

                <Dialog
                    onOpenChange={() => setConfirmationName('')}
                >
                    <DialogTrigger asChild>
                        <Button variant="destructive">
                            Delete organization
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            Are you sure you want to delete this organization?
                        </DialogTitle>
                        <DialogDescription>
                            This will permanently delete{' '}
                            <strong>{organization.name}</strong> and all of its
                            resources. Please type the organization name to
                            confirm.
                        </DialogDescription>

                        <Form
                            action={destroy.url(organization.slug)}
                            method="delete"
                            className="space-y-6"
                        >
                            {({
                                resetAndClearErrors,
                                processing,
                                errors,
                            }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="confirm-organization-name"
                                            className="sr-only"
                                        >
                                            Organization name
                                        </Label>

                                        <Input
                                            id="confirm-organization-name"
                                            type="text"
                                            ref={nameInput}
                                            placeholder={organization.name}
                                            value={confirmationName}
                                            onChange={(e) =>
                                                setConfirmationName(
                                                    e.target.value,
                                                )
                                            }
                                        />

                                        <InputError
                                            message={errors.organization}
                                        />
                                    </div>

                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                variant="secondary"
                                                onClick={() =>
                                                    resetAndClearErrors()
                                                }
                                            >
                                                Cancel
                                            </Button>
                                        </DialogClose>

                                        <Button
                                            variant="destructive"
                                            disabled={
                                                processing ||
                                                confirmationName !==
                                                    organization.name
                                            }
                                            asChild
                                        >
                                            <button type="submit">
                                                Delete organization
                                            </button>
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    );
}

General.layout = withOrganizationSettingsLayout;
