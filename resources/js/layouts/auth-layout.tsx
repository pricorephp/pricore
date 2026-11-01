import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    children,
    title,
    description,
    homeHref,
    ...props
}: {
    children: React.ReactNode;
    title: string;
    description: string;
    homeHref?: string;
}) {
    return (
        <AuthLayoutTemplate
            title={title}
            description={description}
            homeHref={homeHref}
            {...props}
        >
            {children}
        </AuthLayoutTemplate>
    );
}
