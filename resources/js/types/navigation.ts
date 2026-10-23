import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /** The permission needed to see the item; shown to everyone when omitted. */
    permission?: string;
    /** The document kind whose waiting-draft count shows as a badge. */
    draftKind?: string;
};

export type NavGroup = {
    title: string | null;
    items: NavItem[];
};
