import { LayoutGrid, Store, Users } from 'lucide-react';
import { dashboard } from '@/routes';
import { index as adminStores } from '@/routes/admin/stores';
import { index as employees } from '@/routes/employees';
import type { NavGroup, StoreRole } from '@/types';

/**
 * The one shared store menu (spec: Frontend, sidebar groups). Each item is shown
 * only when the user holds its permission; empty groups are hidden. Later tickets
 * add their items to the matching group.
 */
const storeNavigation: NavGroup[] = [
    {
        title: null,
        items: [{ title: 'Bosh sahifa', href: dashboard(), icon: LayoutGrid }],
    },
    { title: 'Savdo', items: [] },
    { title: 'Xarid', items: [] },
    { title: 'Ombor', items: [] },
    { title: 'Pul', items: [] },
    { title: 'Kontragentlar', items: [] },
    { title: 'Hisobotlar', items: [] },
    {
        title: 'Sozlamalar',
        items: [
            {
                title: 'Xodimlar',
                href: employees(),
                icon: Users,
                permission: 'employees.manage',
            },
        ],
    },
];

const superAdminNavigation: NavGroup[] = [
    {
        title: 'Platforma',
        items: [{ title: "Do'konlar", href: adminStores(), icon: Store }],
    },
];

export function navigationFor(
    role: StoreRole | null,
    can: (permission: string) => boolean,
): NavGroup[] {
    const groups =
        role === 'super-admin' ? superAdminNavigation : storeNavigation;

    return groups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) => !item.permission || can(item.permission),
            ),
        }))
        .filter((group) => group.items.length > 0);
}

export function homeFor(role: StoreRole | null) {
    return role === 'super-admin' ? adminStores() : dashboard();
}
