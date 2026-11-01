import TokenScopeFields from '@/components/token-scope-fields';
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

type AccessTokenData = App.Domains.Token.Contracts.Data.AccessTokenData;

interface EditTokenDialogProps {
    updateUrl: string;
    token: AccessTokenData;
    isOrganizationToken: boolean;
    isOpen: boolean;
    onClose: () => void;
}

export default function EditTokenDialog({
    updateUrl,
    token,
    isOrganizationToken,
    isOpen,
    onClose,
}: EditTokenDialogProps) {
    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Edit Token</DialogTitle>
                    <DialogDescription>
                        Change the name and permissions. The token value itself
                        stays the same.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    action={updateUrl}
                    method="patch"
                    onSuccess={onClose}
                    className="space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid space-y-2">
                                <Label htmlFor="name">
                                    Token Name{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    defaultValue={token.name}
                                    autoFocus
                                />
                                {errors.name && (
                                    <p className="text-destructive">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <TokenScopeFields
                                idPrefix="edit-scope"
                                defaultScopes={token.scopes}
                                isOrganizationToken={isOrganizationToken}
                                error={errors.scopes}
                            />

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={onClose}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : 'Save Changes'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
