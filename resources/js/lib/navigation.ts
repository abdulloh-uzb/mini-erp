import {
    ArrowLeftRight,
    Boxes,
    ClipboardCheck,
    ClipboardList,
    Contact,
    HandCoins,
    Landmark,
    LayoutGrid,
    Package,
    ShoppingCart,
    Store,
    Users,
    Wallet,
    Warehouse,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { index as adminStores } from '@/routes/admin/stores';
import { index as cash } from '@/routes/cash';
import { index as counterparties } from '@/routes/counterparties';
import { index as employees } from '@/routes/employees';
import { index as inventoryCounts } from '@/routes/inventory-counts';
import { index as loans } from '@/routes/loans';
import { index as locations } from '@/routes/locations';
import { show as openingBalance } from '@/routes/opening-balance';
import { index as products } from '@/routes/products';
import { index as purchases } from '@/routes/purchases';
import { index as stock } from '@/routes/stock';
import { index as supplierDebts } from '@/routes/supplier-debts';
import { index as transfers } from '@/routes/transfers';
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
    {
        title: 'Xarid',
        items: [
            {
                title: 'Xaridlar',
                href: purchases(),
                icon: ShoppingCart,
                permission: 'purchase.draft',
            },
        ],
    },
    {
        title: 'Ombor',
        items: [
            {
                title: 'Qoldiqlar',
                href: stock(),
                icon: Boxes,
                permission: 'stock-report.view',
            },
            {
                title: "Ko'chirishlar",
                href: transfers(),
                icon: ArrowLeftRight,
                permission: 'transfer.draft',
            },
            {
                title: 'Inventarizatsiyalar',
                href: inventoryCounts(),
                icon: ClipboardCheck,
                permission: 'inventory-count.draft',
            },
            {
                title: 'Mahsulotlar',
                href: products(),
                icon: Package,
                permission: 'products.view',
            },
            {
                title: 'Omborlar',
                href: locations(),
                icon: Warehouse,
                permission: 'locations.manage',
            },
        ],
    },
    {
        title: 'Pul',
        items: [
            {
                title: 'Kassa',
                href: cash(),
                icon: Wallet,
                permission: 'cash-operations.view',
            },
            {
                title: 'Yetkazib beruvchi qarzlari',
                href: supplierDebts(),
                icon: Landmark,
                permission: 'supplier-debts.manage',
            },
            {
                title: 'Qarzlar',
                href: loans(),
                icon: HandCoins,
                permission: 'loans.manage',
            },
        ],
    },
    {
        title: 'Kontragentlar',
        items: [
            {
                title: 'Kontragentlar',
                href: counterparties(),
                icon: Contact,
                permission: 'counterparties.view',
            },
        ],
    },
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
            {
                title: "Boshlang'ich qoldiq",
                href: openingBalance(),
                icon: ClipboardList,
                permission: 'opening-balance.manage',
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
