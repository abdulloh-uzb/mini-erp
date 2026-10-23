import { Head } from '@inertiajs/react';
import SupplierDebtController from '@/actions/App/Http/Controllers/SupplierDebtController';
import { CashOperationDialog } from '@/components/cash-form';
import { EmptyState, ListPageHeader, ListTable } from '@/components/list-page';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';
import { index } from '@/routes/supplier-debts';
import type { EnumOption } from '@/types';

type SupplierDebt = {
    id: number;
    name: string;
    phone: string | null;
    balance: string;
};

type Props = {
    debts: SupplierDebt[];
    total: string;
    methods: EnumOption[];
};

export default function SupplierDebtsIndex({ debts, total, methods }: Props) {
    return (
        <>
            <Head title="Yetkazib beruvchi qarzlari" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <ListPageHeader
                    title="Yetkazib beruvchi qarzlari"
                    description="Xaridlarning to'lanmagan qismi va boshlang'ich qarzlar. To'lov kassadan chiqadi va qarzni kamaytiradi."
                />

                {debts.length === 0 ? (
                    <EmptyState message="Yetkazib beruvchilarga qarz yo'q" />
                ) : (
                    <>
                        <p className="text-sm">
                            Jami qarz:{' '}
                            <span className="text-lg font-semibold tabular-nums">
                                {formatMoney(total)}
                            </span>
                        </p>
                        <ListTable
                            columns={[
                                'Yetkazib beruvchi',
                                'Telefon',
                                'Qarz',
                                '',
                            ]}
                        >
                            {debts.map((debt) => (
                                <tr key={debt.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {debt.name}
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">
                                        {debt.phone ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 font-medium tabular-nums">
                                        {formatMoney(debt.balance)}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <SupplierPaymentDialog
                                            debt={debt}
                                            methods={methods}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </ListTable>
                    </>
                )}
            </div>
        </>
    );
}

/** To'lash: the supplier is fixed; amount up to the debt, method and note. */
function SupplierPaymentDialog({
    debt,
    methods,
}: {
    debt: SupplierDebt;
    methods: EnumOption[];
}) {
    return (
        <CashOperationDialog
            title={`${debt.name}ga to'lov`}
            description={`Qarz: ${formatMoney(debt.balance)}. Summa qarzdan va kassadagi mablag'dan oshmasligi kerak.`}
            trigger={
                <Button variant="outline" size="sm">
                    To'lash
                </Button>
            }
            form={SupplierDebtController.pay.form()}
            methods={methods}
        >
            {() => (
                <input type="hidden" name="counterparty_id" value={debt.id} />
            )}
        </CashOperationDialog>
    );
}

SupplierDebtsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Yetkazib beruvchi qarzlari',
            href: index(),
        },
    ],
};
