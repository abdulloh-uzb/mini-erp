import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import LoanController from '@/actions/App/Http/Controllers/LoanController';
import { CashOperationDialog, selectClassName } from '@/components/cash-form';
import CounterpartyQuickCreate from '@/components/counterparty-quick-create';
import InputError from '@/components/input-error';
import { EmptyState, ListPageHeader, ListTable } from '@/components/list-page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate, formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/loans';
import type { EnumOption, LoanRow, PickerOption } from '@/types';

type Props = {
    loans: LoanRow[];
    counterparties: PickerOption[];
    directions: EnumOption[];
    methods: EnumOption[];
};

export default function LoansIndex({
    loans,
    counterparties,
    directions,
    methods,
}: Props) {
    const newLoan = (
        <NewLoanDialog
            counterparties={counterparties}
            directions={directions}
            methods={methods}
        />
    );

    return (
        <>
            <Head title="Qarzlar" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <ListPageHeader
                    title="Qarzlar"
                    description="Berilgan va olingan qarzlar. Asosiy summa va qaytarishlar sof foydaga kirmaydi, faqat foiz kiradi."
                    action={newLoan}
                />

                {loans.length === 0 ? (
                    <EmptyState message="Hali qarz yo'q" action={newLoan} />
                ) : (
                    <ListTable
                        columns={[
                            'Kontragent',
                            'Turi',
                            'Asosiy',
                            'Qaytarilgan',
                            'Qoldiq',
                            'Muddat',
                        ]}
                    >
                        {loans.map((loan) => (
                            <tr
                                key={loan.id}
                                className="cursor-pointer border-t hover:bg-muted/40"
                                onClick={() => router.visit(show(loan.id))}
                            >
                                <td className="px-4 py-3 font-medium">
                                    {loan.counterparty}
                                </td>
                                <td className="px-4 py-3">{loan.direction}</td>
                                <td className="px-4 py-3 tabular-nums">
                                    {formatMoney(loan.principal)}
                                </td>
                                <td className="px-4 py-3 tabular-nums">
                                    {formatMoney(loan.repaid)}
                                </td>
                                <td className="px-4 py-3 font-medium tabular-nums">
                                    {formatMoney(loan.balance)}
                                </td>
                                <td
                                    className={cn(
                                        'px-4 py-3 tabular-nums',
                                        loan.overdue &&
                                            'font-medium text-red-600 dark:text-red-400',
                                    )}
                                >
                                    {formatDate(loan.due_date)}
                                    {loan.overdue && " · muddati o'tgan"}
                                </td>
                            </tr>
                        ))}
                    </ListTable>
                )}
            </div>
        </>
    );
}

/** Yangi qarz: Turi, Kontragent, Asosiy summa, To'lov usuli, Qaytarish muddati, Izoh. */
function NewLoanDialog({
    counterparties,
    directions,
    methods,
}: {
    counterparties: PickerOption[];
    directions: EnumOption[];
    methods: EnumOption[];
}) {
    const [counterpartyId, setCounterpartyId] = useState<number | ''>('');

    return (
        <CashOperationDialog
            title="Yangi qarz"
            description="Saqlanganda asosiy summa kassadan chiqadi (berilgan) yoki kassaga kiradi (olingan)."
            trigger={
                <Button>
                    <Plus />
                    Yangi qarz
                </Button>
            }
            form={LoanController.store.form()}
            methods={methods}
            amountName="principal"
            amountLabel="Asosiy summa"
        >
            {(errors) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="loan-direction">Turi</Label>
                        <select
                            id="loan-direction"
                            name="direction"
                            className={selectClassName}
                        >
                            {directions.map((direction) => (
                                <option key={direction.id} value={direction.id}>
                                    {direction.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.direction} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="loan-counterparty">Kontragent</Label>
                        <div className="flex gap-2">
                            <select
                                id="loan-counterparty"
                                name="counterparty_id"
                                required
                                value={counterpartyId}
                                onChange={(event) =>
                                    setCounterpartyId(
                                        Number(event.target.value) || '',
                                    )
                                }
                                className={selectClassName}
                            >
                                <option value="">Tanlang</option>
                                {counterparties.map((counterparty) => (
                                    <option
                                        key={counterparty.id}
                                        value={counterparty.id}
                                    >
                                        {counterparty.name}
                                    </option>
                                ))}
                            </select>
                            <CounterpartyQuickCreate
                                trigger={
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        aria-label="Yangi kontragent"
                                    >
                                        <Plus />
                                    </Button>
                                }
                                onCreated={(counterparty) =>
                                    setCounterpartyId(counterparty.id)
                                }
                            />
                        </div>
                        <InputError message={errors.counterparty_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="loan-due-date">
                            Qaytarish muddati (ixtiyoriy)
                        </Label>
                        <Input id="loan-due-date" name="due_date" type="date" />
                        <InputError message={errors.due_date} />
                    </div>
                </>
            )}
        </CashOperationDialog>
    );
}

LoansIndex.layout = {
    breadcrumbs: [
        {
            title: 'Qarzlar',
            href: index(),
        },
    ],
};
