import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
};

export type SidebarNavItem = {
    key: string;
    title: string;
    href?: string | null;
    coming_soon?: boolean;
    icon?: LucideIcon;
};

export type SidebarNavGroup = {
    label: string | null;
    items: SidebarNavItem[];
};
