import InfoBox from '@/components/info-box';
import InputError from '@/components/input-error';
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
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { toast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { destroy, registrationOptions, store } from '@/routes/passkey';
import { edit as editProfile } from '@/routes/profile';
import { passkeys as passkeysIndex } from '@/routes/settings';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, router } from '@inertiajs/react';
import { UserCancelledError } from '@laravel/passkeys';
import { usePasskeyRegister } from '@laravel/passkeys/react';
import { Fingerprint, Plus, Trash2 } from 'lucide-react';
import { DateTime } from 'luxon';
import { type FormEvent, useState } from 'react';

type PasskeyData = App.Domains.Auth.Contracts.Data.PasskeyData;

interface PasskeysPageProps {
    passkeys: PasskeyData[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Settings',
        href: editProfile().url,
    },
    {
        title: 'Passkeys',
        href: passkeysIndex().url,
    },
];

function formatDate(value: string | null): string {
    return value
        ? DateTime.fromISO(value).toLocaleString(DateTime.DATE_MED)
        : 'Unknown';
}

export default function Passkeys({ passkeys }: PasskeysPageProps) {
    const [addDialogOpen, setAddDialogOpen] = useState(false);
    const [name, setName] = useState('');
    const [selectedPasskey, setSelectedPasskey] = useState<PasskeyData | null>(
        null,
    );

    const { register, isLoading, error, errorInstance, isSupported } =
        usePasskeyRegister({
            routes: {
                options: registrationOptions.url(),
                submit: store.url(),
            },
            onSuccess: () => {
                setAddDialogOpen(false);
                setName('');
                toast({
                    title: 'Success',
                    description: 'Passkey added.',
                    variant: 'success',
                });
                router.reload({ only: ['passkeys'] });
            },
        });

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();
        register(name.trim());
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Passkeys" />

            <SettingsLayout>
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h3 className="text-lg font-medium">Passkeys</h3>
                            <p className="text-muted-foreground">
                                Sign in with your fingerprint, face or device
                                PIN instead of a password
                            </p>
                        </div>
                        <Button
                            onClick={() => setAddDialogOpen(true)}
                            disabled={!isSupported}
                        >
                            <Plus className="h-4 w-4" />
                            Add Passkey
                        </Button>
                    </div>

                    <div className="rounded-lg border bg-card px-4 py-2">
                        {passkeys.length === 0 ? (
                            <div className="my-2 rounded-lg border border-dashed p-8 text-center">
                                <p className="text-muted-foreground">
                                    {isSupported
                                        ? 'No passkeys yet. Add one to sign in without a password.'
                                        : 'This browser does not support passkeys.'}
                                </p>
                            </div>
                        ) : (
                            <div className="grid space-y-2">
                                {passkeys.map((passkey, index) => (
                                    <div key={passkey.id}>
                                        {index > 0 && <Separator />}
                                        <div className="flex items-start justify-between py-3">
                                            <div className="flex flex-1 items-start gap-3">
                                                <Fingerprint className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                                                <div className="space-y-0.5">
                                                    <h3 className="font-medium">
                                                        {passkey.name}
                                                    </h3>
                                                    <div className="flex flex-wrap gap-x-3 gap-y-0.5 text-sm text-muted-foreground">
                                                        {passkey.authenticator && (
                                                            <span>
                                                                {
                                                                    passkey.authenticator
                                                                }
                                                            </span>
                                                        )}
                                                        <span>
                                                            Added:{' '}
                                                            {formatDate(
                                                                passkey.createdAt,
                                                            )}
                                                        </span>
                                                        <span>
                                                            Last used:{' '}
                                                            {passkey.lastUsedAt
                                                                ? DateTime.fromISO(
                                                                      passkey.lastUsedAt,
                                                                  ).toRelative()
                                                                : 'Never'}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    setSelectedPasskey(passkey)
                                                }
                                                aria-label={`Remove ${passkey.name}`}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <InfoBox
                        title="About passkeys"
                        description="A passkey is stored on your device or in your password manager and can only be used on this site. Signing in with a passkey verifies your identity on the device itself, so it skips the two-factor code."
                    />
                </div>

                <Dialog
                    open={addDialogOpen}
                    onOpenChange={(open) =>
                        !isLoading && setAddDialogOpen(open)
                    }
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Add Passkey</DialogTitle>
                            <DialogDescription>
                                Give this passkey a name so you can recognize it
                                later, then follow your browser's prompt.
                            </DialogDescription>
                        </DialogHeader>

                        <form onSubmit={handleSubmit} className="space-y-5">
                            <div className="grid space-y-2">
                                <Label htmlFor="passkey-name">Name</Label>
                                <Input
                                    id="passkey-name"
                                    value={name}
                                    onChange={(event) =>
                                        setName(event.target.value)
                                    }
                                    required
                                    maxLength={255}
                                    placeholder="e.g. MacBook Touch ID"
                                    autoFocus
                                />
                                <InputError
                                    message={
                                        errorInstance instanceof
                                        UserCancelledError
                                            ? undefined
                                            : (error ?? undefined)
                                    }
                                />
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => setAddDialogOpen(false)}
                                    disabled={isLoading}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={isLoading || !name.trim()}
                                >
                                    {isLoading && <Spinner />}
                                    Add Passkey
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={selectedPasskey !== null}
                    onOpenChange={() => setSelectedPasskey(null)}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Remove Passkey</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to remove this passkey?
                                You will no longer be able to sign in with it.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="rounded-md border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950">
                            <p className="font-medium text-red-900 dark:text-red-100">
                                Passkey: {selectedPasskey?.name}
                            </p>
                            <p className="mt-1 text-red-700 dark:text-red-300">
                                It stays on your device or in your password
                                manager until you delete it there too.
                            </p>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setSelectedPasskey(null)}
                            >
                                Cancel
                            </Button>
                            {selectedPasskey && (
                                <Form
                                    {...destroy.form(selectedPasskey.id)}
                                    onSuccess={() => setSelectedPasskey(null)}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Removing...'
                                                : 'Remove Passkey'}
                                        </Button>
                                    )}
                                </Form>
                            )}
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </SettingsLayout>
        </AppLayout>
    );
}
