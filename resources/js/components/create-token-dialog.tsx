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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Form } from '@inertiajs/react';

interface CreateTokenDialogProps {
    organizationSlug: string;
    isOpen: boolean;
    onClose: () => void;
}

export default function CreateTokenDialog({
    organizationSlug,
    isOpen,
    onClose,
}: CreateTokenDialogProps) {
    const storeUrl = `/organizations/${organizationSlug}/settings/tokens`;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create Access Token</DialogTitle>
                    <DialogDescription>
                        Create a new token for API access to this organization.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    action={storeUrl}
                    method="post"
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="space-y-2">
                                <Label htmlFor="name">
                                    Token Name{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    placeholder="My API Token"
                                    autoFocus
                                />
                                {errors.name && (
                                    <p className="text-sm text-destructive">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="expires_at">
                                    Expiration (optional)
                                </Label>
                                <Select name="expires_at" defaultValue="never">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Never expires" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="never">
                                            Never expires
                                        </SelectItem>
                                        <SelectItem value={getDaysFromNow(30)}>
                                            30 days
                                        </SelectItem>
                                        <SelectItem value={getDaysFromNow(90)}>
                                            90 days
                                        </SelectItem>
                                        <SelectItem value={getDaysFromNow(365)}>
                                            1 year
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.expires_at && (
                                    <p className="text-sm text-destructive">
                                        {errors.expires_at}
                                    </p>
                                )}
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={onClose}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Creating...'
                                        : 'Create Token'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function getDaysFromNow(days: number): string {
    const date = new Date();
    date.setDate(date.getDate() + days);
    return date.toISOString();
}
