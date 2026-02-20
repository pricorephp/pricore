import CreateOrganizationDialog from '@/components/create-organization-dialog';
import { EmptyState } from '@/components/empty-state';
import HeadingSmall from '@/components/heading-small';
import InfoBox from '@/components/info-box';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, Plus, Users } from 'lucide-react';
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
                        <Plus className="size-4" />
                        Create Organization
                    </Button>
                </div>

                {auth.organizations.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="No organizations yet"
                        description="Create your first organization to start managing private Composer packages."
                        action={{
                            label: 'Create Your First Organization',
                            onClick: () => setCreateDialogOpen(true),
                        }}
                    />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {auth.organizations.map((org) => (
                            <Link
                                key={org.uuid}
                                href={`/organizations/${org.slug}`}
                            >
                                <Card className="group">
                                    <CardHeader>
                                        <CardTitle className="flex items-start justify-between gap-2">
                                            <div className="flex items-center gap-2.5">
                                                <div className="rounded-lg bg-muted/50 p-2 transition-colors group-hover:bg-muted">
                                                    <Users className="h-4 w-4 text-muted-foreground" />
                                                </div>
                                                <span className="transition-colors group-hover:text-primary">
                                                    {org.name}
                                                </span>
                                            </div>
                                            <ArrowUpRight className="h-4 w-4 text-muted-foreground/50 transition-all group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-muted-foreground" />
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-muted-foreground">
                                            @{org.slug}
                                        </p>
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}

                <InfoBox
                    title="What are Organizations?"
                    description="Organizations are workspaces for managing your private Composer packages. Each organization has its own packages, repositories, and API tokens. You can create multiple organizations for different projects or teams."
                />
            </div>

            <CreateOrganizationDialog
                isOpen={createDialogOpen}
                onClose={() => setCreateDialogOpen(false)}
            />
        </AppLayout>
    );
}
