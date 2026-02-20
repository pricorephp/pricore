import { store } from '@/actions/App/Domains/Organization/Http/Controllers/OrganizationController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form } from '@inertiajs/react';

interface CreateOrganizationDialogProps {
    isOpen: boolean;
    onClose: () => void;
}

export default function CreateOrganizationDialog({
    isOpen,
    onClose,
}: CreateOrganizationDialogProps) {
    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create Organization</DialogTitle>
                    <DialogDescription>
                        Create a new organization to manage private Composer
                        packages.
                    </DialogDescription>
                </DialogHeader>

                <Form action={store.url()} method="post" onSuccess={onClose}>
                    {({ processing, errors }) => (
                        <>
                            <div className="mb-4 space-y-2">
                                <Label htmlFor="name">
                                    Organization Name{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    placeholder="My Company"
                                    autoFocus
                                />
                                {errors.name && (
                                    <p className="text-destructive">
                                        {errors.name}
                                    </p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    A unique URL slug will be automatically
                                    generated from the name.
                                </p>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={onClose}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Creating...'
                                        : 'Create Organization'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
