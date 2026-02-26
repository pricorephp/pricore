import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';

const statusMessages: Record<number, { title: string; description: string }> = {
    403: {
        title: 'Forbidden',
        description: "You don't have permission to access this page.",
    },
    404: {
        title: 'Page not found',
        description:
            "The page you're looking for doesn't exist or has been moved.",
    },
    500: {
        title: 'Server error',
        description: 'Something went wrong on our end. Please try again later.',
    },
    503: {
        title: 'Service unavailable',
        description:
            "We're currently performing maintenance. Please check back soon.",
    },
};

export default function Error({ status }: { status: number }) {
    const { title, description } = statusMessages[status] ?? {
        title: 'Error',
        description: 'An unexpected error occurred.',
    };

    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-[#ECEAE6] p-6 md:p-10 dark:bg-background">
            <Head title={`${status} - ${title}`} />

            <div className="flex w-full max-w-md flex-col gap-6">
                <Link
                    href="/"
                    className="flex items-center gap-2 self-center font-medium"
                >
                    <AppLogoIcon className="size-9 fill-current text-[var(--foreground)] dark:text-white" />
                </Link>

                <div className="rounded-xl border border-transparent bg-white px-10 py-8 shadow-sm dark:border-border dark:bg-card">
                    <div className="flex flex-col items-center gap-4 text-center">
                        <span className="text-5xl font-bold text-stone-500 tabular-nums">
                            {status}
                        </span>
                        <div className="space-y-2">
                            <h1 className="text-xl font-medium">{title}</h1>
                            <p className="text-muted-foreground">
                                {description}
                            </p>
                        </div>
                        <Button asChild className="mt-2">
                            <Link href="/">Go back home</Link>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}
