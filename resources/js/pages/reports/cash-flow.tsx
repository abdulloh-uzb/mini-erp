import { Head, Link } from '@inertiajs/react';
import { EmptyState, ListPageHeader, ListTable } from '@/components/list-page';
import { ReportPeriodForm, ReportStat } from '@/components/report';
import { formatDate, formatMoney } from '@/lib/format';
import { index as cash } from '@/routes/cash';
import { cashFlow } from '@/routes/reports';

type InOut = { in: string; out: string };

type Report = {
    totals: InOut & { net: string };
    by_type: (InOut & { type: string; label: string })[];
    by_category: (InOut & { name: string })[];
    by_day: (InOut & { date: string; net: string })[];
};

type Props = {
    report: Report;
    filters: { from: string; to: string };
};

function InOutCells({ row }: { row: InOut & { net?: string } }) {
    return (
        <>
            <td className="px-4 py-3 tabular-nums">{formatMoney(row.in)}</td>
            <td className="px-4 py-3 tabular-nums">{formatMoney(row.out)}</td>
            {row.net !== undefined && (
                <td className="px-4 py-3 tabular-nums">
                    {formatMoney(row.net)}
                </td>
            )}
        </>
    );
}

export default function CashFlow({ report, filters }: Props) {
    const { totals } = report;

    return (
        <>
            <Head title="Pul oqimi" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <ListPageHeader
                    title="Pul oqimi"
                    description="Davrdagi barcha kassa operatsiyalari: qarzlar va qarz to'lovlari ham."
                />

                <ReportPeriodForm
                    filters={filters}
                    url={(query) => cashFlow.url({ query })}
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <ReportStat label="Kirim" value={formatMoney(totals.in)} />
                    <ReportStat
                        label="Chiqim"
                        value={formatMoney(totals.out)}
                    />
                    <ReportStat
                        label="Qoldiq"
                        value={formatMoney(totals.net)}
                    />
                </div>

                {report.by_day.length === 0 ? (
                    <EmptyState message="Bu davrda kassa operatsiyasi yo'q" />
                ) : (
                    <>
                        <section className="grid gap-2">
                            <h2 className="font-semibold">Turi bo'yicha</h2>
                            <ListTable columns={['Turi', 'Kirim', 'Chiqim']}>
                                {report.by_type.map((row) => (
                                    <tr key={row.type} className="border-t">
                                        <td className="px-4 py-3 font-medium">
                                            {row.label}
                                        </td>
                                        <InOutCells row={row} />
                                    </tr>
                                ))}
                            </ListTable>
                        </section>

                        {report.by_category.length > 0 && (
                            <section className="grid gap-2">
                                <h2 className="font-semibold">
                                    Xarajat turi bo'yicha
                                </h2>
                                <ListTable
                                    columns={[
                                        'Xarajat turi',
                                        'Kirim',
                                        'Chiqim',
                                    ]}
                                >
                                    {report.by_category.map((row) => (
                                        <tr key={row.name} className="border-t">
                                            <td className="px-4 py-3 font-medium">
                                                {row.name}
                                            </td>
                                            <InOutCells row={row} />
                                        </tr>
                                    ))}
                                </ListTable>
                            </section>
                        )}

                        <section className="grid gap-2">
                            <h2 className="font-semibold">Kunlar bo'yicha</h2>
                            <ListTable
                                columns={['Sana', 'Kirim', 'Chiqim', 'Qoldiq']}
                            >
                                {report.by_day.map((row) => (
                                    <tr key={row.date} className="border-t">
                                        <td className="px-4 py-3 font-medium">
                                            <Link
                                                href={cash({
                                                    query: {
                                                        from: row.date,
                                                        to: row.date,
                                                    },
                                                })}
                                                className="underline underline-offset-4"
                                            >
                                                {formatDate(row.date)}
                                            </Link>
                                        </td>
                                        <InOutCells row={row} />
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

CashFlow.layout = {
    breadcrumbs: [
        {
            title: 'Pul oqimi',
            href: cashFlow(),
        },
    ],
};
