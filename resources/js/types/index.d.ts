import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface BreadcrumbDropdownItem {
    id: string;
    title: string;
    href: string;
    active?: boolean;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
    dropdown?: {
        items: BreadcrumbDropdownItem[];
        action?: {
            label: string;
            dialog?: React.ComponentType<{
                isOpen: boolean;
                onClose: () => void;
            }>;
        };
    };
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export type SharedData = App.Http.Data.SharedData & {
    [key: string]: unknown;
};

export type User = App.Http.Data.UserData;
