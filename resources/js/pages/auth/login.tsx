import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { SharedData } from '@/types';
import { Form, Head, usePage } from '@inertiajs/react';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    githubEnabled?: boolean;
}

export default function Login({
    status,
    canResetPassword,
    canRegister,
    githubEnabled,
}: LoginProps) {
    const { flash } = usePage<SharedData>().props;

    return (
        <AuthLayout
            title="Log in to your account"
            description="Sign in to your account to continue"
        >
            <Head title="Log in" />

            {githubEnabled && (
                <>
                    <Button variant="outline" className="h-10 w-full" asChild>
                        <a href="/auth/github/redirect">
                            <svg
                                className="size-4"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                            >
                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z" />
                            </svg>
                            Continue with GitHub
                        </a>
                    </Button>

                    <div className="relative">
                        <div className="absolute inset-0 flex items-center">
                            <Separator />
                        </div>
                        <div className="relative flex justify-center text-xs uppercase">
                            <span className="bg-white px-2 text-muted-foreground dark:bg-card">
                                or
                            </span>
                        </div>
                    </div>
                </>
            )}

            <Form
                action={store.url()}
                method="post"
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">Password</Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-xs"
                                            tabIndex={5}
                                        >
                                            Forgot password?
                                        </TextLink>
                                    )}
                                </div>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Password"
                                />
                                <InputError message={errors.password} />
                            </div>
                        </div>

                        <input type="hidden" name="remember" value="1" />

                        <Button
                            type="submit"
                            className="h-10 w-full"
                            tabIndex={4}
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            Log in
                        </Button>
                    </>
                )}
            </Form>

            {flash?.error && (
                <div className="text-center text-sm font-medium text-red-600">
                    {flash.error}
                </div>
            )}

            {status && (
                <div className="text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            {canRegister && (
                <div className="text-center text-muted-foreground">
                    Don&apos;t have an account?{' '}
                    <TextLink href={register()} tabIndex={5}>
                        Sign up
                    </TextLink>
                </div>
            )}
        </AuthLayout>
    );
}
