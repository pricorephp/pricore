import { destroy } from '@/actions/App/Domains/Repository/Http/Controllers/RepositoryController';
import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { createOrganizationBreadcrumb } from '@/lib/breadcrumbs';
import { Head, router, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;
type RepositoryData = App.Domains.Repository.Contracts.Data.RepositoryData;

interface RepositoryEditProps {
    organization: OrganizationData;
    repository: RepositoryData;
}

export default function RepositoryEdit({
    organization,
    repository,
}: RepositoryEditProps) {
    const { auth } = usePage<{
        auth: { organizations: OrganizationData[] };
    }>().props;
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    const handleDelete = () => {
        setIsDeleting(true);
        router.delete(
            destroy.url({
                organization: organization.slug,
                repository: repository.uuid,
            }),
            {
                onFinish: () => {
                    setIsDeleting(false);
                    setIsDeleteOpen(false);
                },
            },
        );
    };

    const breadcrumbs = [
        createOrganizationBreadcrumb(organization, auth.organizations),
        {
            title: 'Repositories',
            href: `/organizations/${organization.slug}/repositories`,
        },
        {
            title: repository.name,
            href: `/organizations/${organization.slug}/repositories/${repository.uuid}`,
        },
        {
            title: 'Edit',
            href: `/organizations/${organization.slug}/repositories/${repository.uuid}/edit`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${repository.name} - ${organization.name}`} />

            <div className="mx-auto w-7xl space-y-6 p-6">
                <HeadingSmall
                    title={`Edit ${repository.name}`}
                    description="Manage repository settings"
                />

                <Card className="border-destructive/50 bg-destructive/2.5">
                    <CardHeader>
                        <CardTitle className="text-destructive">
                            Danger Zone
                        </CardTitle>
                        <CardDescription>
                            Irreversible actions for this repository
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-center justify-between rounded-lg border border-destructive/20 bg-destructive/5 p-4">
                            <div className="space-y-1">
                                <p className="font-medium text-destructive">
                                    Delete Repository
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Permanently remove this repository and
                                    unlink all associated packages.
                                </p>
                            </div>
                            <Button
                                variant="destructive"
                                onClick={() => setIsDeleteOpen(true)}
                            >
                                <Trash2 className="mr-2 size-4" />
                                Delete Repository
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Dialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete Repository</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete{' '}
                                <span className="font-semibold text-foreground">
                                    {repository.name}
                                </span>
                                ? This action cannot be undone and will stop
                                updates for linked packages.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setIsDeleteOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                disabled={isDeleting}
                                onClick={handleDelete}
                            >
                                {isDeleting
                                    ? 'Deleting...'
                                    : 'Delete Repository'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
