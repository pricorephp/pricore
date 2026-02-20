import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard } from '@/routes';
import { Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

interface AuthLayoutProps {
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: PropsWithChildren<AuthLayoutProps>) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-[#ECEAE6] p-6 md:p-10 dark:bg-background">
            <div className="flex w-full max-w-md flex-col gap-6">
                <Link
                    href={dashboard.url()}
                    className="flex items-center gap-2 self-center font-medium"
                >
                    <AppLogoIcon className="size-9 fill-current text-[var(--foreground)] dark:text-white" />
                </Link>

                <div className="rounded-xl border border-transparent bg-white px-10 py-8 shadow-sm dark:border-border dark:bg-card">
                    <div className="flex flex-col gap-6">
                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">{title}</h1>
                            <p className="text-muted-foreground">
                                {description}
                            </p>
                        </div>
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
