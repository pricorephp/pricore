import {
    destroy,
    store,
    update,
} from '@/actions/App/Domains/Organization/Http/Controllers/MemberController';
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
import AppLayout from '@/layouts/app-layout';
import OrganizationSettingsLayout from '@/layouts/organization-settings-layout';
import { Form, router, usePage } from '@inertiajs/react';
import { Trash2, UserPlus } from 'lucide-react';
import { useState } from 'react';

type OrganizationData =
    App.Domains.Organization.Contracts.Data.OrganizationData;
type OrganizationMemberData =
    App.Domains.Organization.Contracts.Data.OrganizationMemberData;

interface Props {
    members: OrganizationMemberData[];
    roleOptions: Record<string, string>;
}

export default function Members({ members, roleOptions }: Props) {
    const page = usePage<{ organization: OrganizationData }>();
    const { organization } = page.props;
    const [addDialogOpen, setAddDialogOpen] = useState(false);

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
                    <p className="text-sm text-muted-foreground">
                        Manage who has access to this organization
                    </p>
                </div>

                <Dialog open={addDialogOpen} onOpenChange={setAddDialogOpen}>
                    <DialogTrigger asChild>
                        <Button>
                            <UserPlus className="h-4 w-4" />
                            Add Member
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Add Member</DialogTitle>
                            <DialogDescription>
                                Invite a user to this organization by their
                                email address
                            </DialogDescription>
                        </DialogHeader>

                        <Form
                            action={store.url(organization.slug)}
                            method="post"
                            onSuccess={() => setAddDialogOpen(false)}
                        >
                            {({ processing, errors }) => (
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            placeholder="user@example.com"
                                            required
                                        />
                                        {errors.email && (
                                            <p className="text-sm text-red-600 dark:text-red-400">
                                                {errors.email}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
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
                                            <p className="text-sm text-red-600 dark:text-red-400">
                                                {errors.role}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex justify-end gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
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
                                                ? 'Adding...'
                                                : 'Add Member'}
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
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Email</TableHead>
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
                                <TableCell className="font-medium">
                                    {member.name}
                                </TableCell>
                                <TableCell>{member.email}</TableCell>
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
                                    {new Date(
                                        member.joinedAt,
                                    ).toLocaleDateString()}
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
        </div>
    );
}

Members.layout = (page: React.ReactNode) => (
    <AppLayout>
        <OrganizationSettingsLayout>{page}</OrganizationSettingsLayout>
    </AppLayout>
);
