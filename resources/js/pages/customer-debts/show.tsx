import { Form, Head, Link } from '@inertiajs/react';
import { Eraser, HandCoins } from 'lucide-react';
import { useState } from 'react';
import CustomerDebtController from '@/actions/App/Http/Controllers/CustomerDebtController';
import { CustomerPaymentDialog } from '@/components/customer-payment-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { ListTable } from '@/components/list-page';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import { formatDateTime, formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index } from '@/routes/customer-debts';
import { show as showSale } from '@/routes/sales';
import type { EnumOption } from '@/types';

type DebtSource = {
    id: number;
    type: string;
    document_id: number | null;
    document_number: number | null;
    created_at: string | null;
    amount: string;
    paid: string;
    debt: string;
};

type HistoryEntry = {
    id: number;
    date: string | null;
    type: string;
    amount: string;
    document_id: number | null;
    document_number: number | null;
    payment_method: string | null;
    note: string | null;
    author: string;
};

type Props = {
    customer: {
        id: number;
        name: string;
        phone: string | null;
        note: string | null;
    };
    balance: string;
    sales: DebtSource[];
    history: HistoryEntry[];
    methods: EnumOption[];
};

export default function CustomerDebtShow({
    customer,
    balance,
    sales,
    history,
    methods,
}: Props) {
    const { can } = usePermissions();
    const owes = Number(balance) > 0;

    return (
        <>
            <Head title={`Qarz: ${customer.name}`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={customer.name}
                        description={customer.phone ?? undefined}
                    />
                    {owes && (
                        <div className="flex gap-2">
                            {can('customer-debt-payment.create') && (
                                <CustomerPaymentDialog
                                    customer={{ ...customer, balance }}
                                    methods={methods}
                                    trigger={
                                        <Button>
                                            <HandCoins />
                                            To'lov olish
                                        </Button>
                                    }
                                />
                            )}
                            {can('debt.write-off') && (
                                <WriteOffDialog
                                    customerId={customer.id}
                                    balance={balance}
                                />
                            )}
                        </div>
                    )}
                </div>

                <p className="text-sm">
                    Qarz:{' '}
                    <span className="text-lg font-semibold tabular-nums">
                        {formatMoney(balance)}
                    </span>
                </p>
                {customer.note && (
                    <p className="text-sm text-muted-foreground">
                        Izoh: {customer.note}
                    </p>
                )}

                <section className="flex flex-col gap-2">
                    <h2 className="font-medium">Qarz qanday yopiladi</h2>
                    <p className="text-sm text-muted-foreground">
                        Qarz bitta summa; to'lovlar eng eski sotuvdan boshlab
                        yopadi.
                    </p>
                    {sales.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Qarz yozuvlari yo'q.
                        </p>
                    ) : (
                        <ListTable
                            columns={[
                                'Sana',
                                'Manba',
                                'Summa',
                                "To'langan",
                                'Qarz',
                            ]}
                        >
                            {sales.map((sale) => (
                                <tr
                                    key={sale.id}
                                    className={cn(
                                        'border-t',
                                        Number(sale.debt) === 0 &&
                                            'text-muted-foreground',
                                    )}
                                >
                                    <td className="px-4 py-3 tabular-nums">
                                        {formatDateTime(sale.created_at)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <SourceLabel
                                            type={sale.type}
                                            documentId={sale.document_id}
                                            number={sale.document_number}
                                        />
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">
                                        {formatMoney(sale.amount)}
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">
                                        {formatMoney(sale.paid)}
                                    </td>
                                    <td className="px-4 py-3 font-medium tabular-nums">
                                        {formatMoney(sale.debt)}
                                    </td>
                                </tr>
                            ))}
                        </ListTable>
                    )}
                </section>

                <section className="flex flex-col gap-2">
                    <h2 className="font-medium">Tarix</h2>
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
                        {history.map((entry) => (
                            <tr key={entry.id} className="border-t">
                                <td className="px-4 py-3 tabular-nums">
                                    {formatDateTime(entry.date)}
                                </td>
                                <td className="px-4 py-3">
                                    <SourceLabel
                                        type={entry.type}
                                        documentId={entry.document_id}
                                        number={entry.document_number}
                                    />
                                </td>
                                <td className="px-4 py-3">
                                    {entry.payment_method ?? '—'}
                                </td>
                                <td
                                    className={cn(
                                        'px-4 py-3 tabular-nums',
                                        Number(entry.amount) < 0 &&
                                            'text-green-700 dark:text-green-400',
                                    )}
                                >
                                    {Number(entry.amount) > 0 ? '+' : ''}
                                    {formatMoney(entry.amount)}
                                </td>
                                <td className="px-4 py-3 text-muted-foreground">
                                    {entry.note}
                                </td>
                                <td className="px-4 py-3">{entry.author}</td>
                            </tr>
                        ))}
                    </ListTable>
                </section>
            </div>
        </>
    );
}

/** The entry's kind, linking to the sale it came from when there is one. */
function SourceLabel({
    type,
    documentId,
    number,
}: {
    type: string;
    documentId: number | null;
    number: number | null;
}) {
    if (documentId === null) {
        return <>{type}</>;
    }

    return (
        <Link href={showSale(documentId)} className="hover:underline">
            {type} №{number}
        </Link>
    );
}

/** Hisobdan chiqarish (owner only): amount up to the debt, "To'liq", and a note. No money moves. */
function WriteOffDialog({
    customerId,
    balance,
}: {
    customerId: number;
    balance: string;
}) {
    const [open, setOpen] = useState(false);
    const [amount, setAmount] = useState('');

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);
                setAmount('');
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Eraser />
                    Hisobdan chiqarish
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Qarzni hisobdan chiqarish</DialogTitle>
                <DialogDescription>
                    Qarz: {formatMoney(balance)}. Undirib bo'lmaydigan qarz
                    kamayadi va «Umidsiz qarz» xarajati sifatida yoziladi; pul
                    harakati bo'lmaydi. Buni o'zgartirib bo'lmaydi.
                </DialogDescription>
                <Form
                    {...CustomerDebtController.writeOff.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => {
                        setOpen(false);
                        setAmount('');
                    }}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="counterparty_id"
                                value={customerId}
                            />
                            <div className="grid gap-2">
                                <Label htmlFor="write-off-amount">Summa</Label>
                                <div className="flex gap-2">
                                    <Input
                                        id="write-off-amount"
                                        name="amount"
                                        inputMode="decimal"
                                        required
                                        autoFocus
                                        placeholder="0"
                                        value={amount}
                                        onChange={(event) =>
                                            setAmount(event.target.value)
                                        }
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            setAmount(String(Number(balance)))
                                        }
                                    >
                                        To'liq
                                    </Button>
                                </div>
                                <InputError message={errors.amount} />
                                <InputError message={errors.counterparty_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="write-off-note">
                                    Izoh (ixtiyoriy)
                                </Label>
                                <Input id="write-off-note" name="note" />
                                <InputError message={errors.note} />
                            </div>
                            <DialogFooter className="gap-2">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => setOpen(false)}
                                >
                                    Bekor qilish
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    Hisobdan chiqarish
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

CustomerDebtShow.layout = {
    breadcrumbs: [{ title: 'Mijoz qarzlari', href: index() }],
};
