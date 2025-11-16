import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { useIsMobile } from '@/hooks/use-mobile';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { ChevronsUpDown } from 'lucide-react';

export function NavUser() {
    const { auth } = usePage<SharedData>().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();
    const isCollapsed = state === 'collapsed';

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild disabled={isCollapsed}>
                        <SidebarMenuButton
                            size="lg"
                            className="group text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
                            data-test="sidebar-menu-button"
                        >
                            <UserInfo user={auth.user} />
                            {!isCollapsed && (
                                <ChevronsUpDown className="ml-auto size-4" />
                            )}
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    {!isCollapsed && (
                        <DropdownMenuContent
                            className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg bg-white dark:bg-neutral-950"
                            align="end"
                            side={isMobile ? 'bottom' : 'bottom'}
                        >
                            <UserMenuContent user={auth.user} />
                        </DropdownMenuContent>
                    )}
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
