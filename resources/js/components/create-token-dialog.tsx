import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { API_SCOPE_GROUPS } from '@/lib/token-scopes';
import { Form } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';

interface CreateTokenDialogProps {
    storeUrl: string;
    description: string;
    isOpen: boolean;
    onClose: () => void;
}

export default function CreateTokenDialog({
    storeUrl,
    description,
    isOpen,
    onClose,
}: CreateTokenDialogProps) {
    const [showApiScopes, setShowApiScopes] = useState(false);

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Create Access Token</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <Form action={storeUrl} method="post" className="space-y-5">
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
                                    placeholder="e.g. Production server"
                                    autoFocus
                                />
                                {errors.name && (
                                    <p className="text-destructive">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="grid space-y-2">
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
                                    <p className="text-destructive">
                                        {errors.expires_at}
                                    </p>
                                )}
                            </div>

                            {/* Every token can install packages with Composer. */}
                            <input
                                type="hidden"
                                name="scopes[]"
                                value="composer"
                            />

                            <div className="rounded-md border">
                                <button
                                    type="button"
                                    onClick={() => setShowApiScopes((v) => !v)}
                                    className="flex w-full items-center justify-between gap-3 p-3 text-left"
                                    aria-expanded={showApiScopes}
                                >
                                    <span>
                                        <span className="text-sm font-medium">
                                            API access
                                        </span>
                                        <span className="mt-0.5 block text-sm text-muted-foreground">
                                            Optional — let this token automate
                                            Pricore through the REST API.
                                        </span>
                                    </span>
                                    {showApiScopes ? (
                                        <ChevronDown className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    ) : (
                                        <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground" />
                                    )}
                                </button>

                                {showApiScopes && (
                                    <div className="grid gap-3 border-t p-3">
                                        <p className="text-sm text-muted-foreground">
                                            Pick the permissions this token
                                            needs. It can never exceed your own
                                            access.
                                        </p>
                                        {API_SCOPE_GROUPS.map((group) => (
                                            <div
                                                key={group.label}
                                                className="grid space-y-1.5"
                                            >
                                                <span className="text-xs font-medium text-muted-foreground">
                                                    {group.label}
                                                </span>
                                                <div className="flex flex-wrap gap-x-4 gap-y-2">
                                                    {group.scopes.map(
                                                        (scope) => (
                                                            <div
                                                                key={
                                                                    scope.value
                                                                }
                                                                className="flex items-center gap-2"
                                                            >
                                                                <Checkbox
                                                                    id={`scope-${scope.value}`}
                                                                    name="scopes[]"
                                                                    value={
                                                                        scope.value
                                                                    }
                                                                />
                                                                <Label
                                                                    htmlFor={`scope-${scope.value}`}
                                                                    className="font-normal"
                                                                >
                                                                    {
                                                                        scope.label
                                                                    }
                                                                </Label>
                                                            </div>
                                                        ),
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                        {errors.scopes && (
                                            <p className="text-destructive">
                                                {errors.scopes}
                                            </p>
                                        )}
                                    </div>
                                )}
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
