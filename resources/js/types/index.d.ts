import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

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

export interface SharedData {
    name: string;
    version: string | null;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    flash?: {
        status?: string;
        error?: string;
    };

    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    avatar_url?: string | null;
    github_nickname?: string | null;
    has_github_connected?: boolean;
    has_password: boolean;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;

    [key: string]: unknown; // This allows for additional properties...
}
