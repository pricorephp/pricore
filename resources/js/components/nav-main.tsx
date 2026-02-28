import { cn, resolveUrl } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage, type InertiaLinkProps } from '@inertiajs/react';

export function NavMain({ items = [] }: { items?: NavItem[] }) {
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
        <nav className="flex flex-col gap-1 px-2">
            {items.map((item) => {
                const active = isActive(item.href);
                return (
                    <Link
                        key={item.title}
                        href={item.href}
                        className={cn(
                            'flex flex-col items-center gap-1 rounded-md px-2 py-2.5 text-center transition-colors',
                            'font-medium text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                            active &&
                                'bg-sidebar-accent text-sidebar-accent-foreground',
                        )}
                    >
                        {item.icon && (
                            <item.icon
                                className="size-5"
                                strokeWidth={active ? 2.25 : 1.75}
                            />
                        )}
                        <span className="text-[10px] leading-tight">
                            {item.title}
                        </span>
                    </Link>
                );
            })}
        </nav>
    );
}
