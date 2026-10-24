import { Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { ListTabs } from '@/components/list-page';
import OpeningBalanceCard from '@/components/opening-balance-card';
import { ReportPeriodForm } from '@/components/report';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    formatDate,
    formatDateTime,
    formatMoney,
    paymentMethodLabels,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as cash } from '@/routes/cash';
import { index as customerDebts } from '@/routes/customer-debts';
import { create as newSale } from '@/routes/sales';
import { create as newTransfer } from '@/routes/transfers';

type PendingDrafts = {
    kind: string;
    label: string;
    count: number;
    href: string;
    drafts: {
        id: number;
        number: number;
        author: string;
        updated_at: string | null;
        href: string;
    }[];
}[];

type Owner = {
    pendingDrafts: PendingDrafts;
    period: {
        gross_sales: string;
        gross_profit: string;
        cash_in: string;
        expenses: string;
        /** What customers owe at the end of the period. */
        debit: string;
        /** What the store owes suppliers at the end of the period. */
        credit: string;
        pieces_sold: number;
    };
    cashBalances: Record<string, string>;
    debts: {
        customer: string;
        supplier: string;
        loans_given: string;
        loans_received: string;
    };
};

type Employee = {
    myDrafts: {
        id: number;
        kind: string;
        label: string;
        number: number;
        updated_at: string | null;
        href: string;
    }[];
    mySalesToday: { sales_count: number; revenue: string };
};

type Period = 'day' | 'week' | 'month' | 'year' | 'custom';

type Filters = { period: Period; from: string; to: string };

type Props = {
    openingBalancePending: boolean;
    owner?: Owner;
    employee?: Employee;
    filters: Filters;
};

const periodTabs: { period: Period; label: string }[] = [
    { period: 'day', label: 'Kunlik' },
    { period: 'week', label: 'Haftalik' },
    { period: 'month', label: 'Oylik' },
    { period: 'year', label: 'Yillik' },
    { period: 'custom', label: 'Maxsus' },
];

function Figure({ label, value }: { label: string; value: string }) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-2xl tabular-nums">{value}</CardTitle>
            </CardHeader>
        </Card>
    );
}

/** A period figure on its own background colour. */
function ColoredFigure({
    label,
    value,
    className,
}: {
    label: string;
    value: string;
    className: string;
}) {
    return (
        <div className={cn('grid gap-2 rounded-xl p-5 shadow-xs', className)}>
            <div className="text-sm font-medium opacity-80">{label}</div>
            <div className="text-2xl font-semibold tabular-nums">{value}</div>
        </div>
    );
}

/**
 * Kunlik / Haftalik / Oylik / Yillik / Maxsus; Maxsus also shows the Dan–Gacha dates.
 */
function PeriodFilter({ filters }: { filters: Filters }) {
    const periodUrl = (period: Period) =>
        dashboard.url({
            query:
                period === 'custom'
                    ? { period, from: filters.from, to: filters.to }
                    : { period },
        });

    return (
        <div className="flex flex-wrap items-end gap-3">
            <ListTabs
                tabs={periodTabs.map((tab) => ({
                    label: tab.label,
                    href: periodUrl(tab.period),
                    active: filters.period === tab.period,
                }))}
            />
            {filters.period === 'custom' && (
                <ReportPeriodForm
                    filters={filters}
                    url={(range) =>
                        dashboard.url({
                            query: { period: 'custom', ...range },
                        })
                    }
                />
            )}
        </div>
    );
}

function OwnerHome({ owner, filters }: { owner: Owner; filters: Filters }) {
    const { period, cashBalances, debts, pendingDrafts } = owner;

    return (
        <>
            <section className="grid gap-3">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 className="font-semibold">Ko'rsatkichlar</h2>
                    <span className="text-sm text-muted-foreground">
                        {filters.from === filters.to
                            ? formatDate(filters.from)
                            : `${formatDate(filters.from)} – ${formatDate(filters.to)}`}
                    </span>
                </div>
                <PeriodFilter filters={filters} />
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <ColoredFigure
                        label="Yalpi savdo"
                        value={formatMoney(period.gross_sales)}
                        className="bg-green-100 text-green-950 dark:bg-green-950 dark:text-green-100"
                    />
                    <ColoredFigure
                        label="Yalpi daromad"
                        value={formatMoney(period.gross_profit)}
                        className="bg-yellow-100 text-yellow-950 dark:bg-yellow-950 dark:text-yellow-100"
                    />
                    <ColoredFigure
                        label="Kirim"
                        value={formatMoney(period.cash_in)}
                        className="bg-blue-100 text-blue-950 dark:bg-blue-950 dark:text-blue-100"
                    />
                    <ColoredFigure
                        label="Xarajat"
                        value={formatMoney(period.expenses)}
                        className="bg-pink-100 text-pink-950 dark:bg-pink-950 dark:text-pink-100"
                    />
                    <ColoredFigure
                        label="Debit (mijozlar qarzi)"
                        value={formatMoney(period.debit)}
                        className="bg-red-100 text-red-950 dark:bg-red-950 dark:text-red-100"
                    />
                    <ColoredFigure
                        label="Kredit (yetkazib beruvchilarga qarz)"
                        value={formatMoney(period.credit)}
                        className="bg-[#f1e0c5] text-[#4a2f14] dark:bg-[#3b2a17] dark:text-[#f1e0c5]"
                    />
                    <ColoredFigure
                        label="Sotilgan mahsulotlar soni"
                        value={`${period.pieces_sold} dona`}
                        className="bg-green-800 text-white dark:bg-green-900"
                    />
                </div>
            </section>

            <section className="grid gap-3">
                <h2 className="font-semibold">Kassa</h2>
                <div className="grid gap-4 sm:grid-cols-3">
                    {Object.entries(cashBalances).map(([method, amount]) => (
                        <Figure
                            key={method}
                            label={paymentMethodLabels[method] ?? method}
                            value={formatMoney(amount)}
                        />
                    ))}
                </div>
            </section>

            <section className="grid gap-3">
                <h2 className="font-semibold">Qarzlar</h2>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Figure
                        label="Mijozlar qarzi"
                        value={formatMoney(debts.customer)}
                    />
                    <Figure
                        label="Yetkazib beruvchilarga qarz"
                        value={formatMoney(debts.supplier)}
                    />
                    <Figure
                        label="Berilgan qarzlar"
                        value={formatMoney(debts.loans_given)}
                    />
                    <Figure
                        label="Olingan qarzlar"
                        value={formatMoney(debts.loans_received)}
                    />
                </div>
            </section>

            {pendingDrafts.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Bajarishni kutayotgan qoralamalar</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        {pendingDrafts.map((group) => (
                            <div key={group.kind} className="grid gap-2">
                                <div className="flex items-center justify-between">
                                    <h3 className="font-medium">
                                        {group.label} ({group.count})
                                    </h3>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={group.href}>Ochish</Link>
                                    </Button>
                                </div>
                                <ul className="divide-y rounded-lg border text-sm">
                                    {group.drafts.map((draft) => (
                                        <li key={draft.id}>
                                            <Link
                                                href={draft.href}
                                                className="flex flex-wrap justify-between gap-2 px-4 py-2 hover:bg-muted/50"
                                            >
                                                <span>
                                                    №{draft.number} ·{' '}
                                                    {draft.author}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {formatDateTime(
                                                        draft.updated_at,
                                                    )}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}
        </>
    );
}

function EmployeeHome({ employee }: { employee: Employee }) {
    return (
        <>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {[
                    { label: 'Yangi sotuv', href: newSale() },
                    { label: 'Yangi transfer', href: newTransfer() },
                    { label: "Qarz to'lovi", href: customerDebts() },
                    { label: 'Xarajat', href: cash() },
                ].map((action) => (
                    <Button
                        key={action.label}
                        asChild
                        size="lg"
                        className="h-20 text-lg"
                    >
                        <Link href={action.href}>{action.label}</Link>
                    </Button>
                ))}
            </div>

            <section className="grid gap-4 sm:grid-cols-2">
                <Figure
                    label="Mening sotuvlarim bugun"
                    value={String(employee.mySalesToday.sales_count)}
                />
                <Figure
                    label="Bugungi tushum"
                    value={formatMoney(employee.mySalesToday.revenue)}
                />
            </section>

            <Card>
                <CardHeader>
                    <CardTitle>Mening qoralamalarim</CardTitle>
                </CardHeader>
                <CardContent>
                    {employee.myDrafts.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Qoralama yo'q
                        </p>
                    ) : (
                        <ul className="divide-y rounded-lg border text-sm">
                            {employee.myDrafts.map((draft) => (
                                <li key={`${draft.kind}-${draft.id}`}>
                                    <Link
                                        href={draft.href}
                                        className="flex flex-wrap justify-between gap-2 px-4 py-2 hover:bg-muted/50"
                                    >
                                        <span>
                                            {draft.label} №{draft.number}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {formatDateTime(draft.updated_at)}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
        </>
    );
}

export default function Dashboard({
    openingBalancePending,
    owner,
    employee,
    filters,
}: Props) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Bosh sahifa" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`Xush kelibsiz, ${auth.user.name}`}
                    description={auth.store?.name}
                />
                {openingBalancePending && <OpeningBalanceCard />}
                {owner && <OwnerHome owner={owner} filters={filters} />}
                {employee && <EmployeeHome employee={employee} />}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Bosh sahifa',
            href: dashboard(),
        },
    ],
};
