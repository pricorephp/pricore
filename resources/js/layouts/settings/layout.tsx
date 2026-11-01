import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn, isSameUrl, resolveUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { gitCredentials, organizations, passkeys } from '@/routes/settings';
import { index as connectedAppsIndex } from '@/routes/settings/connected-apps';
import { index as tokensIndex } from '@/routes/settings/tokens';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    Fingerprint,
    GitBranch,
    Key,
    Lock,
    Paintbrush,
    Plug,
    ShieldCheck,
    User,
    Users,
} from 'lucide-react';
import { type PropsWithChildren } from 'react';

const connectedAppsNavItem: NavItem = {
    title: 'Connected Apps',
    href: connectedAppsIndex(),
    icon: Plug,
};

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: User,
    },
    {
        title: 'Password',
        href: editPassword(),
        icon: Lock,
    },
    {
        title: 'Two-Factor Auth',
        href: show(),
        icon: ShieldCheck,
    },
    {
        title: 'Passkeys',
        href: passkeys(),
        icon: Fingerprint,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: Paintbrush,
    },
    {
        title: 'Git Providers',
        href: gitCredentials(),
        icon: GitBranch,
    },
    {
        title: 'Access Tokens',
        href: tokensIndex(),
        icon: Key,
    },
    connectedAppsNavItem,
    {
        title: 'Organizations',
        href: organizations.url(),
        icon: Users,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { mcpEnabled } = usePage<SharedData>().props;

    // When server-side rendering, we only render the layout on the client...
    if (typeof window === 'undefined') {
        return null;
    }

    const currentPath = window.location.pathname;

    return (
        <div className="mx-auto w-full max-w-7xl min-w-0 space-y-6 p-6">
            <div>
                <h1 className="mb-0.5 text-xl font-medium">
                    Personal Settings
                </h1>
                <p className="text-muted-foreground">
                    Manage your profile and account settings
                </p>
            </div>

            <Separator />

            <div className="flex flex-col space-y-8 lg:flex-row lg:space-y-0 lg:space-x-12">
                <aside className="w-full shrink-0 lg:w-48">
                    <nav className="flex space-x-2 lg:flex-col lg:space-y-1 lg:space-x-0">
                        {sidebarNavItems
                            .filter(
                                (item) =>
                                    mcpEnabled || item !== connectedAppsNavItem,
                            )
                            .map((item, index) => (
                                <Button
                                    key={`${resolveUrl(item.href)}-${index}`}
                                    size="sm"
                                    variant="ghost"
                                    asChild
                                    className={cn('w-full justify-start', {
                                        'bg-muted': isSameUrl(
                                            currentPath,
                                            item.href,
                                        ),
                                    })}
                                >
                                    <Link href={item.href}>
                                        {item.icon && (
                                            <item.icon className="h-4 w-4" />
                                        )}
                                        {item.title}
                                    </Link>
                                </Button>
                            ))}
                    </nav>
                </aside>

                <div className="w-full min-w-0 flex-1">{children}</div>
            </div>
        </div>
    );
}
