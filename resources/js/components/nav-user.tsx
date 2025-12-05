import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

export function NavUser() {
    const { auth } = usePage<SharedData>().props;
    const getInitials = useInitials();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    className="flex w-full items-center justify-center rounded-md p-1.5 hover:bg-sidebar-accent focus:outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring"
                    data-test="sidebar-menu-button"
                >
                    <Avatar className="size-8">
                        <AvatarImage
                            src={auth.user.avatar}
                            alt={auth.user.name}
                        />
                        <AvatarFallback className="bg-sidebar-accent text-xs text-sidebar-accent-foreground">
                            {getInitials(auth.user.name)}
                        </AvatarFallback>
                    </Avatar>
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                className="min-w-56 rounded-lg bg-white dark:bg-neutral-950"
                align="center"
                side="right"
            >
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
