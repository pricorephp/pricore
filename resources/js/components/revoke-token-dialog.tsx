import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Form } from '@inertiajs/react';

interface RevokeTokenDialogProps {
    tokenUuid: string;
    tokenName: string;
    organizationSlug: string;
    isOpen: boolean;
    onClose: () => void;
}

export default function RevokeTokenDialog({
    tokenUuid,
    tokenName,
    organizationSlug,
    isOpen,
    onClose,
}: RevokeTokenDialogProps) {
    const deleteUrl = `/organizations/${organizationSlug}/settings/tokens/${tokenUuid}`;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Revoke Token</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to revoke this token? This action
                        cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <div className="rounded-md border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950">
                    <p className="text-sm font-medium text-red-900 dark:text-red-100">
                        Token: {tokenName}
                    </p>
                    <p className="mt-1 text-sm text-red-700 dark:text-red-300">
                        Any applications using this token will immediately lose
                        access.
                    </p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Form action={deleteUrl} method="delete">
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                {processing ? 'Revoking...' : 'Revoke Token'}
                            </Button>
                        )}
                    </Form>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
