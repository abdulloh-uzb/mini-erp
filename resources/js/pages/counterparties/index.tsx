import { Form, Head } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';
import CounterpartyController from '@/actions/App/Http/Controllers/CounterpartyController';
import CounterpartyQuickCreate from '@/components/counterparty-quick-create';
import InputError from '@/components/input-error';
import {
    EmptyState,
    FilteredEmptyState,
    ListPageHeader,
    ListPagination,
    ListSearch,
    ListTable,
    ListToolbar,
} from '@/components/list-page';
import PhoneInput from '@/components/phone-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import { formatPhone } from '@/lib/phone';
import { index } from '@/routes/counterparties';
import type { Paginated } from '@/types';

type Counterparty = {
    id: number;
    name: string;
    phone: string | null;
    note: string | null;
};

type Props = {
    counterparties: Paginated<Counterparty>;
    filters: {
        search: string;
    };
};

export default function CounterpartiesIndex({
    counterparties,
    filters,
}: Props) {
    const { can } = usePermissions();
    const canCreate = can('counterparties.create');
    const canManage = can('counterparties.manage');

    return (
        <>
            <Head title="Kontragentlar" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <ListPageHeader
                    title="Kontragentlar"
                    description="Mijozlar, yetkazib beruvchilar va qarz bo'yicha tomonlar — bitta ro'yxat."
                    action={canCreate && <CounterpartyQuickCreate withNote />}
                />

                <ListToolbar>
                    <ListSearch
                        value={filters.search}
                        placeholder="Nomi yoki telefon"
                    />
                </ListToolbar>

                {counterparties.data.length === 0 ? (
                    filters.search !== '' ? (
                        <FilteredEmptyState
                            message="Mos kontragent topilmadi"
                            clearHref={index.url()}
                        />
                    ) : (
                        <EmptyState
                            message="Hali kontragent yo'q"
                            action={
                                canCreate && (
                                    <CounterpartyQuickCreate withNote />
                                )
                            }
                        />
                    )
                ) : (
                    <>
                        <ListTable
                            columns={[
                                'Nomi',
                                'Telefon',
                                'Izoh',
                                canManage ? '' : null,
                            ].filter((column) => column !== null)}
                        >
                            {counterparties.data.map((counterparty) => (
                                <tr key={counterparty.id} className="border-t">
                                    <td className="px-4 py-3 font-medium">
                                        {counterparty.name}
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">
                                        {counterparty.phone ? (
                                            formatPhone(counterparty.phone)
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {counterparty.note}
                                    </td>
                                    {canManage && (
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end">
                                                <EditCounterpartyDialog
                                                    counterparty={counterparty}
                                                />
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </ListTable>
                        <ListPagination paginator={counterparties} />
                    </>
                )}
            </div>
        </>
    );
}

function EditCounterpartyDialog({
    counterparty,
}: {
    counterparty: Counterparty;
}) {
    const [open, setOpen] = useState(false);
    const idPrefix = `counterparty-${counterparty.id}`;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Pencil />
                    Tahrirlash
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Kontragentni tahrirlash</DialogTitle>
                <DialogDescription>{counterparty.name}</DialogDescription>
                <Form
                    {...CounterpartyController.update.form(counterparty.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`${idPrefix}-name`}>Nomi</Label>
                                <Input
                                    id={`${idPrefix}-name`}
                                    name="name"
                                    defaultValue={counterparty.name}
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`${idPrefix}-phone`}>
                                    Telefon raqami (ixtiyoriy)
                                </Label>
                                <PhoneInput
                                    id={`${idPrefix}-phone`}
                                    name="phone"
                                    defaultValue={counterparty.phone ?? ''}
                                    autoComplete="off"
                                />
                                <InputError message={errors.phone} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor={`${idPrefix}-note`}>
                                    Izoh (ixtiyoriy)
                                </Label>
                                <Input
                                    id={`${idPrefix}-note`}
                                    name="note"
                                    defaultValue={counterparty.note ?? ''}
                                />
                                <InputError message={errors.note} />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Bekor qilish
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Saqlash
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

CounterpartiesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Kontragentlar',
            href: index(),
        },
    ],
};
