import { Head } from '@inertiajs/react';
import { ListPageHeader } from '@/components/list-page';
import { ReportStat } from '@/components/report';
import { formatMoney, paymentMethodLabels } from '@/lib/format';
import { balance } from '@/routes/reports';

type Report = {
    cash: Record<string, string>;
    cash_total: string;
    debts: {
        customer: string;
        supplier: string;
        loans_given: string;
        loans_received: string;
    };
};

export default function Balance({ report }: { report: Report }) {
    const { debts } = report;

    return (
        <>
            <Head title="Balans" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <ListPageHeader
                    title="Balans"
                    description="Do'konning hozirgi holati."
                />

                <section className="grid gap-2">
                    <h2 className="font-semibold">Kassa</h2>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {Object.entries(report.cash).map(([method, amount]) => (
                            <ReportStat
                                key={method}
                                label={paymentMethodLabels[method] ?? method}
                                value={formatMoney(amount)}
                            />
                        ))}
                        <ReportStat
                            label="Jami"
                            value={formatMoney(report.cash_total)}
                        />
                    </div>
                </section>

                <section className="grid gap-2">
                    <h2 className="font-semibold">Qarzlar</h2>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <ReportStat
                            label="Mijozlar qarzi"
                            value={formatMoney(debts.customer)}
                        />
                        <ReportStat
                            label="Yetkazib beruvchilarga qarz"
                            value={formatMoney(debts.supplier)}
                        />
                        <ReportStat
                            label="Berilgan qarzlar"
                            value={formatMoney(debts.loans_given)}
                        />
                        <ReportStat
                            label="Olingan qarzlar"
                            value={formatMoney(debts.loans_received)}
                        />
                    </div>
                </section>
            </div>
        </>
    );
}

Balance.layout = {
    breadcrumbs: [
        {
            title: 'Balans',
            href: balance(),
        },
    ],
};
