import {
    destroy,
    store,
    update,
} from '@/actions/App/Domains/Organization/Http/Controllers/MemberController';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useInitials } from '@/hooks/use-initials';
import { withOrganizationSettingsLayout } from '@/layouts/organization-settings-layout';
import { Form, router, usePage } from '@inertiajs/react';
import { Mail, RefreshCw, Trash2, UserPlus } from 'lucide-react';
import { useState } from 'react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;
type OrganizationMemberData =
    App.Domains.Organization.Contracts.Data.OrganizationMemberData;
type OrganizationInvitationData =
    App.Domains.Organization.Contracts.Data.OrganizationInvitationData;

interface Props {
    members: OrganizationMemberData[];
    invitations: OrganizationInvitationData[];
    roleOptions: Record<string, string>;
}

export default function Members({ members, invitations, roleOptions }: Props) {
    const page = usePage<{ organization: OrganizationData }>();
    const { organization } = page.props;
    const [addDialogOpen, setAddDialogOpen] = useState(false);
    const getInitials = useInitials();

    const handleRoleChange = (memberUuid: string, role: string) => {
        router.patch(
            update.url([organization.slug, memberUuid]),
            { role },
            {
                preserveScroll: true,
            },
        );
    };

    const handleRemoveMember = (memberUuid: string) => {
        if (confirm('Are you sure you want to remove this member?')) {
            router.delete(destroy.url([organization.slug, memberUuid]), {
                preserveScroll: true,
            });
        }
    };

    const handleCancelInvitation = (invitationUuid: string) => {
        if (confirm('Are you sure you want to cancel this invitation?')) {
            router.delete(
                `/organizations/${organization.slug}/settings/invitations/${invitationUuid}`,
                { preserveScroll: true },
            );
        }
    };

    const handleResendInvitation = (invitationUuid: string) => {
        router.post(
            `/organizations/${organization.slug}/settings/invitations/${invitationUuid}/resend`,
            {},
            { preserveScroll: true },
        );
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

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-medium">Members</h3>
                    <p className="text-muted-foreground">
                        Manage who has access to this organization
                    </p>
                </div>

                <Dialog open={addDialogOpen} onOpenChange={setAddDialogOpen}>
                    <DialogTrigger asChild>
                        <Button>
                            <UserPlus className="h-4 w-4" />
                            Invite
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Invite Member</DialogTitle>
                            <DialogDescription>
                                Invite a user by email. They'll receive an
                                invitation email they need to accept.
                            </DialogDescription>
                        </DialogHeader>

                        <Form
                            action={store.url(organization.slug)}
                            method="post"
                            onSuccess={() => setAddDialogOpen(false)}
                        >
                            {({ processing, errors }) => (
                                <div className="space-y-4">
                                    <div className="grid space-y-2">
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            placeholder="user@example.com"
                                            required
                                        />
                                        {errors.email && (
                                            <p className="text-red-600 dark:text-red-400">
                                                {errors.email}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid space-y-2">
                                        <Label htmlFor="role">Role</Label>
                                        <Select
                                            name="role"
                                            defaultValue="member"
                                            required
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select a role" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(
                                                    roleOptions,
                                                ).map(([value, label]) => (
                                                    <SelectItem
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.role && (
                                            <p className="text-red-600 dark:text-red-400">
                                                {errors.role}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex justify-end gap-2">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            onClick={() =>
                                                setAddDialogOpen(false)
                                            }
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Inviting...'
                                                : 'Invite'}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>

            <div className="rounded-lg border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead>Member</TableHead>
                            <TableHead>Role</TableHead>
                            <TableHead>Joined</TableHead>
                            <TableHead className="text-right">
                                Actions
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {members.map((member) => (
                            <TableRow key={member.uuid}>
                                <TableCell>
                                    <div className="flex items-center gap-3">
                                        <Avatar className="h-8 w-8">
                                            <AvatarImage
                                                src={member.avatar ?? undefined}
                                                alt={member.name}
                                            />
                                            <AvatarFallback className="bg-gradient-to-br from-neutral-200 to-neutral-300 text-xs text-neutral-600 dark:from-neutral-600 dark:to-neutral-700 dark:text-neutral-300">
                                                {getInitials(member.name)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="flex flex-col">
                                            <span className="font-medium">
                                                {member.name}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {member.email}
                                            </span>
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    {member.role === 'owner' ? (
                                        <Badge
                                            variant={getRoleBadgeVariant(
                                                member.role,
                                            )}
                                        >
                                            {roleOptions[member.role]}
                                        </Badge>
                                    ) : (
                                        <Select
                                            value={member.role}
                                            onValueChange={(role) =>
                                                handleRoleChange(
                                                    member.uuid,
                                                    role,
                                                )
                                            }
                                        >
                                            <SelectTrigger className="w-32">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(roleOptions)
                                                    .filter(
                                                        ([value]) =>
                                                            value !== 'owner',
                                                    )
                                                    .map(([value, label]) => (
                                                        <SelectItem
                                                            key={value}
                                                            value={value}
                                                        >
                                                            {label}
                                                        </SelectItem>
                                                    ))}
                                            </SelectContent>
                                        </Select>
                                    )}
                                </TableCell>
                                <TableCell>
                                    {member.joinedAt
                                        ? new Date(
                                              member.joinedAt,
                                          ).toLocaleDateString()
                                        : '-'}
                                </TableCell>
                                <TableCell className="text-right">
                                    {member.role !== 'owner' && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                handleRemoveMember(member.uuid)
                                            }
                                        >
                                            <Trash2 className="h-4 w-4 text-red-600 dark:text-red-400" />
                                        </Button>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            {invitations.length > 0 && (
                <div className="space-y-3">
                    <h4 className="text-sm font-medium text-muted-foreground">
                        Pending Invitations
                    </h4>
                    <div className="rounded-lg border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Email</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead>Sent</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {invitations.map((invitation) => (
                                    <TableRow key={invitation.uuid}>
                                        <TableCell>
                                            <div className="flex items-center gap-2 text-muted-foreground">
                                                <Mail className="h-4 w-4 shrink-0" />
                                                <span>{invitation.email}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={getRoleBadgeVariant(
                                                    invitation.role,
                                                )}
                                            >
                                                {roleOptions[invitation.role]}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {invitation.createdAt
                                                ? new Date(
                                                      invitation.createdAt,
                                                  ).toLocaleDateString()
                                                : '-'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleResendInvitation(
                                                            invitation.uuid,
                                                        )
                                                    }
                                                    title="Resend invitation"
                                                >
                                                    <RefreshCw className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleCancelInvitation(
                                                            invitation.uuid,
                                                        )
                                                    }
                                                    title="Cancel invitation"
                                                >
                                                    <Trash2 className="h-4 w-4 text-red-600 dark:text-red-400" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </div>
            )}
        </div>
    );
}

Members.layout = withOrganizationSettingsLayout;
