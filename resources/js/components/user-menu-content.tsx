import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { type Appearance, useAppearance } from '@/hooks/use-appearance';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import { gitCredentials } from '@/routes/settings';
import tokens from '@/routes/settings/tokens';
import { type User } from '@/types';
import { Link, router } from '@inertiajs/react';
import { GitBranch, Key, LogOut, Monitor, Moon, Settings, Sun } from 'lucide-react';

interface UserMenuContentProps {
    user: User;
}

const themes: { value: Appearance; icon: typeof Sun; label: string }[] = [
    { value: 'light', icon: Sun, label: 'Light' },
    { value: 'dark', icon: Moon, label: 'Dark' },
    { value: 'system', icon: Monitor, label: 'System' },
];

export function UserMenuContent({ user }: UserMenuContentProps) {
    const cleanup = useMobileNavigation();
    const { appearance, updateAppearance } = useAppearance();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-2 py-1.5 text-left">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full"
                        href={edit()}
                        as="button"
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        Settings
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full"
                        href={gitCredentials()}
                        as="button"
                        prefetch
                        onClick={cleanup}
                    >
                        <GitBranch className="mr-2" />
                        Git Providers
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full"
                        href={tokens.index()}
                        as="button"
                        prefetch
                        onClick={cleanup}
                    >
                        <Key className="mr-2" />
                        Tokens
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <div className="px-2 py-1.5">
                <div className="flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800">
                    {themes.map(({ value, icon: Icon, label }) => (
                        <button
                            key={value}
                            onClick={() => updateAppearance(value)}
                            className={cn(
                                'flex flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-md px-2 py-1 text-xs transition-colors',
                                appearance === value
                                    ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                                    : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
                            )}
                        >
                            <Icon className="size-3.5" />
                            {label}
                        </button>
                    ))}
                </div>
            </div>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}
