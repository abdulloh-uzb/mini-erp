import { Head } from '@inertiajs/react';
import { ListPageHeader } from '@/components/list-page';
import { ReportPeriodForm, ReportStat } from '@/components/report';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import { profitAndLoss } from '@/routes/reports';

type Report = {
    revenue: string;
    cost_of_goods_sold: string;
    gross_profit: string;
    expenses: { name: string; amount: string }[];
    expenses_total: string;
    shortages: string;
    surpluses: string;
    interest: { received: string; paid: string; net: string };
    net_profit: string;
};

type Props = {
    report: Report;
    position: {
        cash_total: string;
        loans_given: string;
        loans_received: string;
    };
    filters: { from: string; to: string };
};

/**
 * One line of the statement. A sign shows how it enters the result; a total line is bold.
 */
function Line({
    label,
    amount,
    sign,
    total = false,
    indent = false,
}: {
    label: string;
    amount: string;
    sign?: '+' | '−';
    total?: boolean;
    indent?: boolean;
}) {
    return (
        <tr className={cn('border-t', total && 'bg-muted/50 font-semibold')}>
            <td className={cn('px-4 py-3', indent && 'pl-10')}>{label}</td>
            <td className="px-4 py-3 text-right tabular-nums">
                {sign && <span className="text-muted-foreground">{sign} </span>}
                {formatMoney(amount)}
            </td>
        </tr>
    );
}

export default function ProfitAndLoss({ report, position, filters }: Props) {
    return (
        <>
            <Head title="Foyda va zarar" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <ListPageHeader
                    title="Foyda va zarar"
                    description="Yetkazib beruvchiga to'lovlar, qarz to'lovlari, qarz summasi va egasi olgan pul kirmaydi."
                />

                <ReportPeriodForm
                    filters={filters}
                    url={(query) => profitAndLoss.url({ query })}
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <ReportStat
                        label="Tushum"
                        value={formatMoney(report.revenue)}
                    />
                    <ReportStat
                        label="Yalpi foyda"
                        value={formatMoney(report.gross_profit)}
                    />
                    <ReportStat
                        label="Sof foyda"
                        value={formatMoney(report.net_profit)}
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <ReportStat
                        label="Kassada hozir"
                        value={formatMoney(position.cash_total)}
                    />
                    <ReportStat
                        label="Berilgan qarzlar"
                        value={formatMoney(position.loans_given)}
                    />
                    <ReportStat
                        label="Olingan qarzlar"
                        value={formatMoney(position.loans_received)}
                    />
                </div>

                <div className="overflow-hidden rounded-xl border">
                    <table className="w-full text-sm">
                        <tbody>
                            <Line label="Tushum" amount={report.revenue} />
                            <Line
                                label="Sotilgan tovar tannarxi"
                                amount={report.cost_of_goods_sold}
                                sign="−"
                            />
                            <Line
                                label="Yalpi foyda"
                                amount={report.gross_profit}
                                total
                            />
                            <Line
                                label="Xarajatlar"
                                amount={report.expenses_total}
                                sign="−"
                            />
                            {report.expenses.map((expense) => (
                                <Line
                                    key={expense.name}
                                    label={expense.name}
                                    amount={expense.amount}
                                    indent
                                />
                            ))}
                            <Line
                                label="Kamomad"
                                amount={report.shortages}
                                sign="−"
                            />
                            <Line
                                label="Ortiqcha"
                                amount={report.surpluses}
                                sign="+"
                            />
                            <Line
                                label="Olingan foiz"
                                amount={report.interest.received}
                                sign="+"
                            />
                            <Line
                                label="Qarz foizi"
                                amount={report.interest.paid}
                                sign="−"
                            />
                            <Line
                                label="Sof foyda"
                                amount={report.net_profit}
                                total
                            />
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

ProfitAndLoss.layout = {
    breadcrumbs: [
        {
            title: 'Foyda va zarar',
            href: profitAndLoss(),
        },
    ],
};
