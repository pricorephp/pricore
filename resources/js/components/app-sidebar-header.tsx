import { Breadcrumbs } from '@/components/breadcrumbs';
import { NavUser } from '@/components/nav-user';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useCommandPalette } from '@/hooks/use-command-palette';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { SearchIcon } from 'lucide-react';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { setOpen } = useCommandPalette();

    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-2 border-b px-6 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1 md:hidden" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="flex items-center gap-4">
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className="flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <SearchIcon className="size-4" />
                    <span className="hidden lg:inline">Search...</span>
                    <kbd className="pointer-events-none hidden rounded border bg-muted px-1.5 font-mono text-[10px] font-medium select-none lg:inline">
                        ⌘K
                    </kbd>
                </button>
                <NavUser />
            </div>
        </header>
    );
}
