import {
    ArrowLeftRight,
    BarChart3,
    Boxes,
    ClipboardCheck,
    ClipboardList,
    Contact,
    HandCoins,
    Handshake,
    Landmark,
    LayoutGrid,
    PieChart,
    Scale,
    TrendingUp,
    Package,
    ScanBarcode,
    ShoppingCart,
    Store,
    Undo2,
    Users,
    Wallet,
    Warehouse,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { index as adminStores } from '@/routes/admin/stores';
import { index as cash } from '@/routes/cash';
import { index as counterparties } from '@/routes/counterparties';
import { index as customerDebts } from '@/routes/customer-debts';
import { index as employees } from '@/routes/employees';
import { index as inventoryCounts } from '@/routes/inventory-counts';
import { index as loans } from '@/routes/loans';
import { index as locations } from '@/routes/locations';
import { show as openingBalance } from '@/routes/opening-balance';
import { index as products } from '@/routes/products';
import { index as purchases } from '@/routes/purchases';
import { create as sale } from '@/routes/sales';
import { index as salesReturns } from '@/routes/sales-returns';
import {
    balance,
    cashFlow,
    profitAndLoss,
    sales as salesReport,
} from '@/routes/reports';
import { index as stock } from '@/routes/stock';
import { index as supplierDebts } from '@/routes/supplier-debts';
import { index as supplierReturns } from '@/routes/supplier-returns';
import { index as transfers } from '@/routes/transfers';
import type { NavGroup, StoreRole } from '@/types';

/**
 * The one shared store menu (spec: Frontend, sidebar groups), ordered by how often each
 * page is used: the till first, then daily sales, stock and purchases, money, reports
 * and setup last. Labels use the words shop staff already say (vozvrat, postavshik,
 * transfer) where the literal Uzbek reads awkwardly. Each item is shown only when the
 * user holds its permission; empty groups are hidden.
 */
const storeNavigation: NavGroup[] = [
    {
        title: null,
        items: [
            { title: 'Bosh sahifa', href: dashboard(), icon: LayoutGrid },
            {
                title: 'Sotuv',
                href: sale(),
                icon: ScanBarcode,
                permission: 'sale.draft',
            },
        ],
    },
    {
        title: 'Savdo',
        items: [
            {
                title: 'Vozvratlar',
                href: salesReturns(),
                icon: Undo2,
                permission: 'sales-return.draft',
                draftKind: 'sales_return',
            },
            {
                title: 'Mijoz qarzlari',
                href: customerDebts(),
                icon: Handshake,
                permission: 'customer-debts.view',
            },
            {
                title: 'Kontragentlar',
                href: counterparties(),
                icon: Contact,
                permission: 'counterparties.view',
            },
        ],
    },
    {
        title: 'Ombor',
        items: [
            {
                title: 'Mahsulotlar',
                href: products(),
                icon: Package,
                permission: 'products.view',
            },
            {
                title: 'Qoldiqlar',
                href: stock(),
                icon: Boxes,
                permission: 'stock-report.view',
            },
            {
                title: 'Transferlar',
                href: transfers(),
                icon: ArrowLeftRight,
                permission: 'transfer.draft',
            },
            {
                title: 'Inventarizatsiya',
                href: inventoryCounts(),
                icon: ClipboardCheck,
                permission: 'inventory-count.draft',
                draftKind: 'inventory_count',
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
        title: 'Xarid',
        items: [
            {
                title: 'Xaridlar',
                href: purchases(),
                icon: ShoppingCart,
                permission: 'purchase.draft',
                draftKind: 'purchase',
            },
            {
                title: 'Postavshikka vozvrat',
                href: supplierReturns(),
                icon: Undo2,
                permission: 'supplier-return.manage',
            },
            {
                title: 'Postavshik qarzlari',
                href: supplierDebts(),
                icon: Landmark,
                permission: 'supplier-debts.manage',
            },
        ],
    },
    {
        title: 'Moliya',
        items: [
            {
                title: 'Kassa',
                href: cash(),
                icon: Wallet,
                permission: 'cash-operations.view',
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
        title: 'Hisobotlar',
        items: [
            {
                title: 'Sotuvlar',
                href: salesReport(),
                icon: BarChart3,
                permission: 'sales-report.view',
            },
            {
                title: 'Foyda va zarar',
                href: profitAndLoss(),
                icon: TrendingUp,
                permission: 'finance-reports.view',
            },
            {
                title: 'Pul oqimi',
                href: cashFlow(),
                icon: PieChart,
                permission: 'finance-reports.view',
            },
            {
                title: 'Balans',
                href: balance(),
                icon: Scale,
                permission: 'finance-reports.view',
            },
        ],
    },
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
