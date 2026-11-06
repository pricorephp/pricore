import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { dashboard } from '@/routes';
import { confirm, confirmOptions } from '@/routes/passkey';
import { store } from '@/routes/password/confirm';
import { Form, Head } from '@inertiajs/react';
import { UserCancelledError } from '@laravel/passkeys';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { Fingerprint } from 'lucide-react';

export default function ConfirmPassword({
    hasPassword,
    hasPasskeys = false,
}: {
    hasPassword: boolean;
    hasPasskeys?: boolean;
}) {
    const { verify, isLoading, error, errorInstance, isSupported } =
        usePasskeyVerify({
            routes: {
                options: confirmOptions.url(),
                submit: confirm.url(),
            },
            onSuccess: (response) => {
                window.location.assign(response.redirect ?? dashboard.url());
            },
        });

    return (
        <AuthLayout
            title="Confirm your password"
            description={
                hasPassword
                    ? 'This is a secure area of the application. Please confirm your password before continuing.'
                    : 'This is a secure area of the application. Please confirm to continue.'
            }
        >
            <Head title="Confirm password" />

            <Form
                action={store.url()}
                method="post"
                resetOnSuccess={['password']}
            >
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        {hasPassword && (
                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    placeholder="Password"
                                    autoComplete="current-password"
                                    autoFocus
                                />

                                <InputError message={errors.password} />
                            </div>
                        )}

                        <div className="flex items-center">
                            <Button
                                className="w-full"
                                disabled={processing}
                                data-test="confirm-password-button"
                            >
                                {processing && <Spinner />}
                                {hasPassword ? 'Confirm password' : 'Continue'}
                            </Button>
                        </div>
                    </div>
                )}
            </Form>

            {hasPassword && hasPasskeys && isSupported && (
                <div className="flex flex-col gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        className="w-full"
                        onClick={verify}
                        disabled={isLoading}
                        data-test="confirm-passkey-button"
                    >
                        {isLoading ? <Spinner /> : <Fingerprint />}
                        Use a passkey instead
                    </Button>
                    <InputError
                        message={
                            errorInstance instanceof UserCancelledError
                                ? undefined
                                : (error ?? undefined)
                        }
                        className="text-center"
                    />
                </div>
            )}
        </AuthLayout>
    );
}
