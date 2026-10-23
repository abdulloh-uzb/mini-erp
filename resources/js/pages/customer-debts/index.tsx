import { Head, Link } from '@inertiajs/react';
import { CustomerPaymentDialog } from '@/components/customer-payment-dialog';
import { EmptyState, ListPageHeader, ListTable } from '@/components/list-page';
import { usePermissions } from '@/hooks/use-permissions';
import { formatMoney } from '@/lib/format';
import { index, show } from '@/routes/customer-debts';
import type { EnumOption } from '@/types';

type CustomerDebt = {
    id: number;
    name: string;
    phone: string | null;
    balance: string;
};

type Props = {
    debts: CustomerDebt[];
    total: string;
    methods: EnumOption[];
};

export default function CustomerDebtsIndex({ debts, total, methods }: Props) {
    const { can } = usePermissions();

    return (
        <>
            <Head title="Mijoz qarzlari" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <ListPageHeader
                    title="Mijoz qarzlari"
                    description="Sotuvlarning to'lanmagan qismi va boshlang'ich qarzlar. To'lov kassaga tushadi va qarzni kamaytiradi."
                />

                {debts.length === 0 ? (
                    <EmptyState message="Mijozlarda qarz yo'q" />
                ) : (
                    <>
                        <p className="text-sm">
                            Jami qarz:{' '}
                            <span className="text-lg font-semibold tabular-nums">
                                {formatMoney(total)}
                            </span>
                        </p>
                        <ListTable columns={['Mijoz', 'Telefon', 'Qarz', '']}>
                            {debts.map((debt) => (
                                <tr key={debt.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        <Link
                                            href={show(debt.id)}
                                            className="hover:underline"
                                        >
                                            {debt.name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">
                                        {debt.phone ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 font-medium tabular-nums">
                                        {formatMoney(debt.balance)}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {can(
                                            'customer-debt-payment.create',
                                        ) && (
                                            <CustomerPaymentDialog
                                                customer={debt}
                                                methods={methods}
                                            />
                                        )}
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

CustomerDebtsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Mijoz qarzlari',
            href: index(),
        },
    ],
};
