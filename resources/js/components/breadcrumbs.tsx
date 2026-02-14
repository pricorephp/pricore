import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { Link, router } from '@inertiajs/react';
import { ChevronDown, Plus } from 'lucide-react';
import { Fragment, useState } from 'react';

function hasDropdown(item: BreadcrumbItemType): item is BreadcrumbItemType & {
    dropdown: NonNullable<BreadcrumbItemType['dropdown']>;
} {
    return 'dropdown' in item && item.dropdown !== undefined;
}

export function Breadcrumbs({
    breadcrumbs,
}: {
    breadcrumbs: BreadcrumbItemType[];
}) {
    return (
        <>
            {breadcrumbs.length > 0 && (
                <Breadcrumb>
                    <BreadcrumbList className="font-mono text-sm">
                        {breadcrumbs.map((item, index) => {
                            const isLast = index === breadcrumbs.length - 1;
                            return (
                                <Fragment key={index}>
                                    <BreadcrumbItem>
                                        {hasDropdown(item) ? (
                                            <BreadcrumbDropdown
                                                item={item}
                                                isLast={isLast}
                                            />
                                        ) : isLast ? (
                                            <BreadcrumbPage>
                                                {item.title}
                                            </BreadcrumbPage>
                                        ) : (
                                            <BreadcrumbLink asChild>
                                                <Link href={item.href}>
                                                    {item.title}
                                                </Link>
                                            </BreadcrumbLink>
                                        )}
                                    </BreadcrumbItem>
                                    {!isLast && <BreadcrumbSeparator />}
                                </Fragment>
                            );
                        })}
                    </BreadcrumbList>
                </Breadcrumb>
            )}
        </>
    );
}

interface BreadcrumbDropdownProps {
    item: BreadcrumbItemType & {
        dropdown: NonNullable<BreadcrumbItemType['dropdown']>;
    };
    isLast: boolean;
}

function BreadcrumbDropdown({ item, isLast }: BreadcrumbDropdownProps) {
    const [actionDialogOpen, setActionDialogOpen] = useState(false);
    const ActionDialog = item.dropdown.action?.dialog;

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button
                        className={cn(
                            '-mx-1 flex items-center gap-1 rounded-md px-1.5 py-0.5',
                            'hover:bg-accent hover:text-accent-foreground',
                            'focus:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                            'transition-colors',
                            isLast
                                ? 'font-normal text-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {item.title}
                        <ChevronDown className="size-3 opacity-60" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" className="min-w-48">
                    {item.dropdown.items.map((dropdownItem) => (
                        <DropdownMenuItem
                            key={dropdownItem.id}
                            onClick={() => router.visit(dropdownItem.href)}
                            className={cn(dropdownItem.active && 'bg-accent')}
                        >
                            {dropdownItem.title}
                        </DropdownMenuItem>
                    ))}
                    {item.dropdown.action && (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onClick={() => setActionDialogOpen(true)}
                            >
                                <Plus className="size-4" />
                                {item.dropdown.action.label}
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
            {ActionDialog && (
                <ActionDialog
                    isOpen={actionDialogOpen}
                    onClose={() => setActionDialogOpen(false)}
                />
            )}
        </>
    );
}
