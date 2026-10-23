import { Head } from '@inertiajs/react';
import { HandCoins, Percent } from 'lucide-react';
import LoanController from '@/actions/App/Http/Controllers/LoanController';
import { CashOperationDialog, SignedAmount } from '@/components/cash-form';
import Heading from '@/components/heading';
import { ListTable } from '@/components/list-page';
import { Button } from '@/components/ui/button';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index } from '@/routes/loans';
import type { EnumOption, LoanRow } from '@/types';

type Operation = {
    id: number;
    date: string | null;
    type: string;
    direction: 'in' | 'out';
    payment_method: string;
    amount: string;
    note: string | null;
    author: string;
};

type Props = {
    loan: LoanRow & {
        note: string | null;
        direction_code: 'given' | 'received';
    };
    operations: Operation[];
    methods: EnumOption[];
};

export default function LoanShow({ loan, operations, methods }: Props) {
    const given = loan.direction_code === 'given';
    const owed = Number(loan.balance) > 0;

    return (
        <>
            <Head title={`Qarz: ${loan.counterparty}`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={loan.counterparty}
                        description={`${loan.direction} qarz · ${formatDateTime(loan.date)}`}
                    />
                    <div className="flex gap-2">
                        {owed && (
                            <CashOperationDialog
                                title="Qaytarish"
                                description={`Qoldiq: ${formatMoney(loan.balance)}. ${given ? 'Pul kassaga qaytadi.' : 'Pul kassadan chiqadi.'}`}
                                trigger={
                                    <Button>
                                        <HandCoins />
                                        Qaytarish
                                    </Button>
                                }
                                form={LoanController.repay.form(loan.id)}
                                methods={methods}
                            />
                        )}
                        <CashOperationDialog
                            title={given ? 'Foiz olish' : "Foiz to'lash"}
                            description={
                                given
                                    ? '«Olingan foiz» daromadi sifatida yoziladi.'
                                    : '«Qarz foizi» xarajati sifatida yoziladi.'
                            }
                            trigger={
                                <Button variant="outline">
                                    <Percent />
                                    {given ? 'Foiz olish' : "Foiz to'lash"}
                                </Button>
                            }
                            form={LoanController.interest.form(loan.id)}
                            methods={methods}
                        />
                    </div>
                </div>

                <div className="grid grid-cols-4 overflow-hidden rounded-xl border">
                    <Figure
                        label="Asosiy"
                        value={formatMoney(loan.principal)}
                    />
                    <Figure
                        label="Qaytarilgan"
                        value={formatMoney(loan.repaid)}
                    />
                    <Figure label="Qoldiq" value={formatMoney(loan.balance)} />
                    <Figure
                        label="Muddat"
                        value={formatDate(loan.due_date)}
                        alert={loan.overdue}
                    />
                </div>

                {loan.note && (
                    <p className="text-sm text-muted-foreground">
                        Izoh: {loan.note}
                    </p>
                )}

                <ListTable
                    columns={[
                        'Sana',
                        'Turi',
                        "To'lov usuli",
                        'Summa',
                        'Izoh',
                        'Muallif',
                    ]}
                >
                    {operations.length === 0 ? (
                        <tr className="border-t">
                            <td
                                colSpan={6}
                                className="px-4 py-6 text-center text-muted-foreground"
                            >
                                Boshlang'ich qoldiqdan kiritilgan qarz, hali
                                operatsiya yo'q.
                            </td>
                        </tr>
                    ) : (
                        operations.map((operation) => (
                            <tr key={operation.id} className="border-t">
                                <td className="px-4 py-3 tabular-nums">
                                    {formatDateTime(operation.date)}
                                </td>
                                <td className="px-4 py-3">{operation.type}</td>
                                <td className="px-4 py-3">
                                    {operation.payment_method}
                                </td>
                                <td className="px-4 py-3">
                                    <SignedAmount
                                        direction={operation.direction}
                                    >
                                        {formatMoney(operation.amount)}
                                    </SignedAmount>
                                </td>
                                <td className="px-4 py-3 text-muted-foreground">
                                    {operation.note}
                                </td>
                                <td className="px-4 py-3">
                                    {operation.author}
                                </td>
                            </tr>
                        ))
                    )}
                </ListTable>
            </div>
        </>
    );
}

function Figure({
    label,
    value,
    alert = false,
}: {
    label: string;
    value: string;
    alert?: boolean;
}) {
    return (
        <div className="border-r p-4 last:border-r-0">
            <div className="text-sm text-muted-foreground">{label}</div>
            <div
                className={cn(
                    'mt-1 text-xl font-semibold tabular-nums',
                    alert && 'text-red-600 dark:text-red-400',
                )}
            >
                {value}
                {alert && (
                    <span className="ml-2 text-sm font-medium">
                        muddati o'tgan
                    </span>
                )}
            </div>
        </div>
    );
}

LoanShow.layout = {
    breadcrumbs: [{ title: 'Qarzlar', href: index() }],
};
