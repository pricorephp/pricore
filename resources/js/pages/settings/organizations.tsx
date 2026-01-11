import LeaveOrganizationController from '@/actions/App/Http/Controllers/Settings/LeaveOrganizationController';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';

import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { organizations } from '@/routes/settings';
import { Building2, LogOut } from 'lucide-react';
import { useState } from 'react';

type OrganizationWithRoleData =
    App.Domains.Organization.Contracts.Data.OrganizationWithRoleData;

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Organizations settings',
        href: organizations.url(),
    },
];

export default function Organizations() {
    const page = usePage<{
        organizations: OrganizationWithRoleData[];
        flash?: {
            status?: string;
            error?: string;
        };
    }>();

    const { organizations, flash } = page.props;
    const [leavingOrgSlug, setLeavingOrgSlug] = useState<string | null>(null);
    const [dialogOpen, setDialogOpen] = useState<string | null>(null);

    const handleLeave = (organizationSlug: string) => {
        setLeavingOrgSlug(organizationSlug);
        setDialogOpen(null);
        router.delete(LeaveOrganizationController.url(organizationSlug), {
            preserveScroll: true,
            onFinish: () => {
                setLeavingOrgSlug(null);
            },
        });
    };

    const getRoleBadgeVariant = (role: string) => {
        switch (role) {
            case 'owner':
                return 'default';
            case 'admin':
                return 'secondary';
            default:
                return 'outline';
        }
    };

    const getRoleLabel = (role: string) => {
        switch (role) {
            case 'owner':
                return 'Owner';
            case 'admin':
                return 'Admin';
            default:
                return 'Member';
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Organizations settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Your Organizations"
                        description="Manage the organizations you belong to"
                    />

                    {flash?.error && (
                        <div className="rounded-md border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950">
                            <p className="text-sm font-medium text-red-800 dark:text-red-200">
                                {flash.error}
                            </p>
                        </div>
                    )}

                    {flash?.status && (
                        <div className="rounded-md border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950">
                            <p className="text-sm font-medium text-green-800 dark:text-green-200">
                                {flash.status}
                            </p>
                        </div>
                    )}

                    {organizations.length === 0 ? (
                        <div className="rounded-lg border border-dashed p-12 text-center">
                            <Building2 className="mx-auto h-12 w-12 text-muted-foreground" />
                            <p className="mt-4 text-sm text-muted-foreground">
                                You are not a member of any organizations yet.
                            </p>
                        </div>
                    ) : (
                        <div className="grid gap-4">
                            {organizations.map(
                                ({ organization, role, isOwner }) => (
                                    <Card key={organization.uuid}>
                                        <CardHeader>
                                            <div className="flex items-start justify-between">
                                                <div className="flex items-start gap-3">
                                                    <Building2 className="mt-1 h-5 w-5 text-muted-foreground" />
                                                    <div>
                                                        <CardTitle className="flex items-center gap-2">
                                                            {organization.name}
                                                            <Badge
                                                                variant={getRoleBadgeVariant(
                                                                    role,
                                                                )}
                                                            >
                                                                {getRoleLabel(
                                                                    role,
                                                                )}
                                                            </Badge>
                                                        </CardTitle>
                                                        <CardDescription className="mt-1">
                                                            @{organization.slug}
                                                        </CardDescription>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={`/organizations/${organization.slug}`}
                                                        >
                                                            View
                                                        </Link>
                                                    </Button>
                                                    {!isOwner && (
                                                        <Dialog
                                                            open={
                                                                dialogOpen ===
                                                                organization.slug
                                                            }
                                                            onOpenChange={(
                                                                open,
                                                            ) =>
                                                                setDialogOpen(
                                                                    open
                                                                        ? organization.slug
                                                                        : null,
                                                                )
                                                            }
                                                        >
                                                            <DialogTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    variant="destructive"
                                                                    size="sm"
                                                                    disabled={
                                                                        leavingOrgSlug ===
                                                                        organization.slug
                                                                    }
                                                                >
                                                                    <LogOut className="size-4" />
                                                                    Leave
                                                                </Button>
                                                            </DialogTrigger>
                                                            <DialogContent>
                                                                <DialogHeader>
                                                                    <DialogTitle>
                                                                        Leave
                                                                        Organization?
                                                                    </DialogTitle>
                                                                    <DialogDescription>
                                                                        Are you
                                                                        sure you
                                                                        want to
                                                                        leave{' '}
                                                                        <strong>
                                                                            {
                                                                                organization.name
                                                                            }
                                                                        </strong>
                                                                        ? You
                                                                        will
                                                                        lose
                                                                        access
                                                                        to all
                                                                        packages
                                                                        and
                                                                        repositories
                                                                        in this
                                                                        organization.
                                                                    </DialogDescription>
                                                                </DialogHeader>
                                                                <DialogFooter>
                                                                    <DialogClose
                                                                        asChild
                                                                    >
                                                                        <Button variant="outline">
                                                                            Cancel
                                                                        </Button>
                                                                    </DialogClose>
                                                                    <Button
                                                                        variant="destructive"
                                                                        onClick={() =>
                                                                            handleLeave(
                                                                                organization.slug,
                                                                            )
                                                                        }
                                                                        disabled={
                                                                            leavingOrgSlug ===
                                                                            organization.slug
                                                                        }
                                                                    >
                                                                        {leavingOrgSlug ===
                                                                        organization.slug
                                                                            ? 'Leaving...'
                                                                            : 'Leave Organization'}
                                                                    </Button>
                                                                </DialogFooter>
                                                            </DialogContent>
                                                        </Dialog>
                                                    )}
                                                </div>
                                            </div>
                                        </CardHeader>
                                    </Card>
                                ),
                            )}
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
