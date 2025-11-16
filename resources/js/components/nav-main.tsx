import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { resolveUrl } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage, type InertiaLinkProps } from '@inertiajs/react';

export function NavMain({
    items = [],
    showLabel = true,
}: {
    items?: NavItem[];
    showLabel?: boolean;
}) {
    const page = usePage();

    const isActive = (href: NonNullable<InertiaLinkProps['href']>) => {
        const resolvedHref = resolveUrl(href);
        const currentUrl = page.url;

        // Exact match
        if (currentUrl === resolvedHref) {
            return true;
        }

        // For organization overview, only match exact URL (not sub-pages)
        if (resolvedHref.match(/^\/organizations\/[^/]+$/)) {
            return currentUrl === resolvedHref;
        }

        // For settings routes, match any settings sub-route
        const settingsMatch = resolvedHref.match(
            /^(\/organizations\/[^/]+\/settings)\//,
        );
        if (settingsMatch) {
            const settingsBase = settingsMatch[1];
            return currentUrl.startsWith(settingsBase + '/');
        }

        // For other routes, use startsWith
        return currentUrl.startsWith(resolvedHref);
    };

    return (
        <SidebarGroup className="px-2 py-0">
            {showLabel && <SidebarGroupLabel>Platform</SidebarGroupLabel>}
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={isActive(item.href)}
                            tooltip={{ children: item.title }}
                        >
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
