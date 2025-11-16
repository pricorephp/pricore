import CreateOrganizationDialog from '@/components/create-organization-dialog';
import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Building2, Plus } from 'lucide-react';
import { useState } from 'react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

export default function Dashboard() {
    const { auth } = usePage<{
        auth: { organizations: OrganizationData[] };
    }>().props;

    const [createDialogOpen, setCreateDialogOpen] = useState(false);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="mx-auto w-7xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <HeadingSmall
                        title="Your Organizations"
                        description="Select an organization to manage packages and repositories"
                    />
                    <Button onClick={() => setCreateDialogOpen(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Create Organization
                    </Button>
                </div>

                {auth.organizations.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-12 text-center">
                        <Building2 className="mx-auto h-12 w-12 text-muted-foreground" />
                        <p className="mt-4 text-sm text-muted-foreground">
                            No organizations yet. Create your first organization
                            to start managing private Composer packages.
                        </p>
                        <Button
                            className="mt-4"
                            variant="outline"
                            onClick={() => setCreateDialogOpen(true)}
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Create Your First Organization
                        </Button>
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {auth.organizations.map((org) => (
                            <Link
                                key={org.uuid}
                                href={`/organizations/${org.slug}`}
                            >
                                <Card className="transition-colors hover:bg-accent/50">
                                    <CardHeader>
                                        <CardTitle className="flex items-start justify-between gap-2">
                                            <div className="flex items-center gap-2">
                                                <Building2 className="h-5 w-5 text-muted-foreground" />
                                                <span>{org.name}</span>
                                            </div>
                                            <ArrowRight className="h-4 w-4 text-muted-foreground" />
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-sm text-muted-foreground">
                                            @{org.slug}
                                        </p>
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}

                <div className="rounded-md border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-800 dark:bg-neutral-950">
                    <p className="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                        What are Organizations?
                    </p>
                    <p className="mt-1 text-sm text-neutral-700 dark:text-neutral-300">
                        Organizations are workspaces for managing your private
                        Composer packages. Each organization has its own
                        packages, repositories, and API tokens. You can create
                        multiple organizations for different projects or teams.
                    </p>
                </div>
            </div>

            <CreateOrganizationDialog
                isOpen={createDialogOpen}
                onClose={() => setCreateDialogOpen(false)}
            />
        </AppLayout>
    );
}
