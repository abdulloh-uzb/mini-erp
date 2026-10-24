import { Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import OpeningBalanceCard from '@/components/opening-balance-card';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDateTime, formatMoney, paymentMethodLabels } from '@/lib/format';
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
    today: {
        revenue: string;
        sales_count: number;
        gross_profit: string;
        cash_received: string;
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

type Props = {
    openingBalancePending: boolean;
    owner?: Owner;
    employee?: Employee;
};

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

function OwnerHome({ owner }: { owner: Owner }) {
    const { today, cashBalances, debts, pendingDrafts } = owner;

    return (
        <>
            <section className="grid gap-3">
                <h2 className="font-semibold">Bugun</h2>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Figure label="Tushum" value={formatMoney(today.revenue)} />
                    <Figure
                        label="Sotuvlar soni"
                        value={String(today.sales_count)}
                    />
                    <Figure
                        label="Yalpi foyda"
                        value={formatMoney(today.gross_profit)}
                    />
                    <Figure
                        label="Kelgan pul"
                        value={formatMoney(today.cash_received)}
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
                {owner && <OwnerHome owner={owner} />}
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
