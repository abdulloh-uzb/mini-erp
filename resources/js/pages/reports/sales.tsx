import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { EmptyState, ListPageHeader, ListTable } from '@/components/list-page';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatDateTime, formatMoney, formatQuantity } from '@/lib/format';
import { sales } from '@/routes/reports';

type Report = {
    totals: {
        revenue: string;
        sales_count: number;
        cost?: string;
        gross_profit?: string;
    };
    by_product: {
        product_id: number;
        product_name: string;
        pieces_per_block: number;
        quantity: number;
        revenue: string;
        profit?: string;
    }[];
    by_employee: {
        user_id: number;
        name: string;
        sales_count: number;
        revenue: string;
        profit?: string;
    }[];
    sales: {
        id: number;
        number: number;
        date: string;
        employee: string;
        customer: string | null;
        total: string;
        paid: string;
        debt: string;
    }[];
};

type Props = {
    report: Report;
    filters: { from: string; to: string };
};

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent className="text-2xl font-semibold tabular-nums">
                {value}
            </CardContent>
        </Card>
    );
}

export default function SalesReport({ report, filters }: Props) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const { totals } = report;
    const showCosts = totals.gross_profit !== undefined;

    function submit(event: FormEvent) {
        event.preventDefault();
        router.get(sales.url({ query: { from, to } }), {}, { replace: true });
    }

    return (
        <>
            <Head title="Sotuvlar hisoboti" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <ListPageHeader
                    title="Sotuvlar hisoboti"
                    description="Bajarilgan sotuvlar, qaytarishlar chegirilgan holda."
                />

                <form
                    onSubmit={submit}
                    className="flex flex-wrap items-end gap-3"
                >
                    <label className="grid gap-1 text-sm">
                        Dan
                        <Input
                            type="date"
                            value={from}
                            onChange={(event) => setFrom(event.target.value)}
                            className="w-44"
                        />
                    </label>
                    <label className="grid gap-1 text-sm">
                        Gacha
                        <Input
                            type="date"
                            value={to}
                            onChange={(event) => setTo(event.target.value)}
                            className="w-44"
                        />
                    </label>
                    <Button type="submit">Ko'rsatish</Button>
                </form>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat label="Tushum" value={formatMoney(totals.revenue)} />
                    <Stat
                        label="Sotuvlar soni"
                        value={String(totals.sales_count)}
                    />
                    {showCosts && (
                        <>
                            <Stat
                                label="Tannarx"
                                value={formatMoney(totals.cost)}
                            />
                            <Stat
                                label="Yalpi foyda"
                                value={formatMoney(totals.gross_profit)}
                            />
                        </>
                    )}
                </div>

                {report.sales.length === 0 ? (
                    <EmptyState message="Bu davrda sotuv yo'q" />
                ) : (
                    <>
                        <section className="grid gap-2">
                            <h2 className="font-semibold">
                                Mahsulotlar bo'yicha
                            </h2>
                            <ListTable
                                columns={[
                                    'Mahsulot',
                                    'Miqdor',
                                    'Tushum',
                                    ...(showCosts ? ['Foyda'] : []),
                                ]}
                            >
                                {report.by_product.map((row) => (
                                    <tr
                                        key={row.product_id}
                                        className="border-t"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {row.product_name}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {formatQuantity(
                                                row.quantity,
                                                row.pieces_per_block,
                                            )}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {formatMoney(row.revenue)}
                                        </td>
                                        {showCosts && (
                                            <td className="px-4 py-3 tabular-nums">
                                                {formatMoney(row.profit)}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </ListTable>
                        </section>

                        <section className="grid gap-2">
                            <h2 className="font-semibold">Xodimlar bo'yicha</h2>
                            <ListTable
                                columns={[
                                    'Xodim',
                                    'Sotuvlar',
                                    'Tushum',
                                    ...(showCosts ? ['Foyda'] : []),
                                ]}
                            >
                                {report.by_employee.map((row) => (
                                    <tr key={row.user_id} className="border-t">
                                        <td className="px-4 py-3 font-medium">
                                            {row.name}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {row.sales_count}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {formatMoney(row.revenue)}
                                        </td>
                                        {showCosts && (
                                            <td className="px-4 py-3 tabular-nums">
                                                {formatMoney(row.profit)}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </ListTable>
                        </section>

                        <section className="grid gap-2">
                            <h2 className="font-semibold">Sotuvlar</h2>
                            <ListTable
                                columns={[
                                    'Sotuv',
                                    'Sana',
                                    'Xodim',
                                    'Mijoz',
                                    'Jami',
                                    "To'langan",
                                    'Qarz',
                                ]}
                            >
                                {report.sales.map((sale) => (
                                    <tr key={sale.id} className="border-t">
                                        <td className="px-4 py-3 font-medium">
                                            №{sale.number}
                                        </td>
                                        <td className="px-4 py-3">
                                            {formatDateTime(sale.date)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {sale.employee}
                                        </td>
                                        <td className="px-4 py-3">
                                            {sale.customer ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {formatMoney(sale.total)}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {formatMoney(sale.paid)}
                                        </td>
                                        <td className="px-4 py-3 tabular-nums">
                                            {formatMoney(sale.debt)}
                                        </td>
                                    </tr>
                                ))}
                            </ListTable>
                        </section>
                    </>
                )}
            </div>
        </>
    );
}

SalesReport.layout = {
    breadcrumbs: [
        {
            title: 'Sotuvlar hisoboti',
            href: sales(),
        },
    ],
};
