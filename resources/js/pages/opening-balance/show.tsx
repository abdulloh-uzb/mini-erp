import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import OpeningBalanceController from '@/actions/App/Http/Controllers/OpeningBalanceController';
import AlertError from '@/components/alert-error';
import { selectClassName } from '@/components/cash-form';
import CounterpartyQuickCreate from '@/components/counterparty-quick-create';
import {
    DocumentAudit,
    DocumentFormActions,
    DocumentLinesTable,
    DocumentStatusBadge,
    DocumentTotals,
    ExecutedLinesTable,
    newLineKey,
    ProductSearch,
} from '@/components/document-form';
import type { DraftLine, LineErrors } from '@/components/document-form';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { ListTable } from '@/components/list-page';
import { SkipOpeningBalance } from '@/components/opening-balance-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate, formatMoney, splitQuantity } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { show } from '@/routes/opening-balance';
import type {
    DocumentData,
    EnumOption,
    PickerOption,
    PickerProduct,
} from '@/types';

type Props = {
    document: DocumentData | null;
    skipped: boolean;
    locations: PickerOption[];
    products: PickerProduct[];
    counterparties: PickerOption[];
    methods: EnumOption[];
    directions: EnumOption[];
};

type Section = 'stock' | 'cash' | 'supplier_debts' | 'loans';

type DraftLoan = {
    key: string;
    direction: string;
    counterparty_id: number | '';
    principal: string;
    due_date: string;
    note: string;
};

type DraftDebt = {
    key: string;
    counterparty_id: number | '';
    amount: string;
};

/** Validation errors keyed by field path (`payments.cash`, `loans.0.principal`). */
type FieldErrors = Record<string, string | undefined>;

const EXECUTE_WARNING =
    "Boshlang'ich qoldiq bir marta bajariladi. Bajarilgan hujjatni o'zgartirib yoki o'chirib bo'lmaydi. Davom etasizmi?";

export default function OpeningBalanceShow({
    document,
    skipped,
    locations,
    products,
    counterparties,
    methods,
    directions,
}: Props) {
    return (
        <>
            <Head title="Boshlang'ich qoldiq" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Boshlang'ich qoldiq"
                        description="Tizimdan foydalanishni boshlaganda do'konda bor bo'lgan tovarlar. Sof foydaga ta'sir qilmaydi."
                    />
                    {document && (
                        <DocumentStatusBadge status={document.status} />
                    )}
                </div>

                {skipped ? (
                    <p className="rounded-xl border p-6 text-muted-foreground">
                        Do'kon boshlang'ich qoldiqsiz ishlayapti («Boshlang'ich
                        qoldiq yo'q» tanlangan).
                    </p>
                ) : document?.status === 'executed' ? (
                    <ExecutedOpeningBalance
                        document={document}
                        methods={methods}
                        directions={directions}
                    />
                ) : (
                    <OpeningBalanceForm
                        document={document}
                        locations={locations}
                        products={products}
                        counterparties={counterparties}
                        methods={methods}
                        directions={directions}
                    />
                )}
            </div>
        </>
    );
}

function ExecutedOpeningBalance({
    document,
    methods,
    directions,
}: {
    document: DocumentData;
    methods: EnumOption[];
    directions: EnumOption[];
}) {
    const [section, setSection] = useState<Section>('stock');
    const total = document.lines.reduce(
        (sum, line) => sum + line.quantity * Number(line.price ?? 0),
        0,
    );
    const directionNames = new Map(
        directions.map((direction) => [direction.id, direction.name]),
    );

    return (
        <div className="flex flex-col gap-4">
            <DocumentAudit document={document} />
            <SectionTabs value={section} onChange={setSection} />
            {section === 'stock' && (
                <>
                    <ExecutedLinesTable
                        document={document}
                        priceLabel="Tannarx/dona"
                        showLocation
                    />
                    <p className="text-sm">
                        Tovarlar qiymati:{' '}
                        <span className="font-semibold">
                            {formatMoney(total)}
                        </span>
                    </p>
                </>
            )}
            {section === 'cash' && (
                <div className="grid max-w-md gap-2 text-sm">
                    {methods.map((method) => (
                        <div
                            key={method.id}
                            className="flex justify-between border-b py-2"
                        >
                            <span>{method.name}</span>
                            <span className="font-medium tabular-nums">
                                {formatMoney(
                                    document.payments?.[method.id] ?? 0,
                                )}
                            </span>
                        </div>
                    ))}
                </div>
            )}
            {section === 'supplier_debts' && (
                <ListTable columns={['Yetkazib beruvchi', 'Qarz summasi']}>
                    {(document.supplier_debts ?? []).map((debt) => (
                        <tr key={debt.id} className="border-t">
                            <td className="px-4 py-3">
                                {debt.counterparty_name}
                            </td>
                            <td className="px-4 py-3 tabular-nums">
                                {formatMoney(debt.amount)}
                            </td>
                        </tr>
                    ))}
                </ListTable>
            )}
            {section === 'loans' && (
                <ListTable
                    columns={[
                        'Turi',
                        'Kontragent',
                        'Asosiy summa',
                        'Muddat',
                        'Izoh',
                    ]}
                >
                    {(document.loans ?? []).map((loan) => (
                        <tr key={loan.id} className="border-t">
                            <td className="px-4 py-3">
                                {directionNames.get(loan.direction)}
                            </td>
                            <td className="px-4 py-3">
                                {loan.counterparty_name}
                            </td>
                            <td className="px-4 py-3 tabular-nums">
                                {formatMoney(loan.principal)}
                            </td>
                            <td className="px-4 py-3 tabular-nums">
                                {formatDate(loan.due_date)}
                            </td>
                            <td className="px-4 py-3 text-muted-foreground">
                                {loan.note}
                            </td>
                        </tr>
                    ))}
                </ListTable>
            )}
            {document.note && (
                <p className="text-sm text-muted-foreground">
                    Izoh: {document.note}
                </p>
            )}
        </div>
    );
}

/** The opening balance's sections; the customer debts tab comes with ticket 08. */
function SectionTabs({
    value,
    onChange,
    withErrors = [],
}: {
    value: Section;
    onChange: (section: Section) => void;
    withErrors?: Section[];
}) {
    const sections: { id: Section; label: string }[] = [
        { id: 'stock', label: 'Tovarlar' },
        { id: 'cash', label: 'Kassa' },
        { id: 'supplier_debts', label: 'Yetkazib beruvchi qarzlari' },
        { id: 'loans', label: 'Qarzlar' },
    ];

    return (
        <div className="inline-flex w-fit rounded-lg bg-muted p-1">
            {sections.map((section) => (
                <button
                    key={section.id}
                    type="button"
                    onClick={() => onChange(section.id)}
                    className={cn(
                        'rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors',
                        value === section.id &&
                            'bg-background text-foreground shadow-xs',
                        withErrors.includes(section.id) &&
                            'text-red-600 dark:text-red-400',
                    )}
                >
                    {section.label}
                </button>
            ))}
        </div>
    );
}

function OpeningBalanceForm({
    document,
    locations,
    products,
    counterparties,
    methods,
    directions,
}: {
    document: DocumentData | null;
    locations: PickerOption[];
    products: PickerProduct[];
    counterparties: PickerOption[];
    methods: EnumOption[];
    directions: EnumOption[];
}) {
    const [section, setSection] = useState<Section>('stock');
    const productsById = useMemo(
        () => new Map(products.map((product) => [product.id, product])),
        [products],
    );
    const [locationId, setLocationId] = useState<number | null>(
        document?.lines.at(-1)?.location_id ?? locations[0]?.id ?? null,
    );

    const form = useForm({
        note: document?.note ?? '',
        lines: (document?.lines ?? []).map((line): DraftLine => {
            const split = splitQuantity(line.quantity, line.pieces_per_block);

            return {
                key: newLineKey(),
                product_id: line.product_id,
                location_id: line.location_id,
                blocks: String(split.blocks),
                pieces: String(split.pieces),
                price: line.price ?? '',
            };
        }),
        payments: Object.fromEntries(
            methods.map((method) => [
                method.id,
                document?.payments?.[method.id] ?? '',
            ]),
        ) as Record<string, string>,
        loans: (document?.loans ?? []).map((loan): DraftLoan => ({
            key: newLineKey(),
            direction: loan.direction,
            counterparty_id: loan.counterparty_id,
            principal: loan.principal,
            due_date: loan.due_date ?? '',
            note: loan.note ?? '',
        })),
        supplier_debts: (document?.supplier_debts ?? []).map(
            (debt): DraftDebt => ({
                key: newLineKey(),
                counterparty_id: debt.counterparty_id,
                amount: debt.amount,
            }),
        ),
    });
    const errors = form.errors as LineErrors & FieldErrors;
    const errorKeys = Object.keys(form.errors);
    const sectionsWithErrors = (
        [
            ['stock', 'lines.'],
            ['cash', 'payments.'],
            ['supplier_debts', 'supplier_debts.'],
            ['loans', 'loans.'],
        ] as [Section, string][]
    )
        .filter(([, prefix]) => errorKeys.some((key) => key.startsWith(prefix)))
        .map(([id]) => id);

    form.transform((data) => ({
        note: data.note,
        payments: Object.fromEntries(
            Object.entries(data.payments).map(([method, amount]) => [
                method,
                amount === '' ? null : amount,
            ]),
        ),
        loans: data.loans.map((loan) => ({
            direction: loan.direction,
            counterparty_id: loan.counterparty_id,
            principal: loan.principal,
            due_date: loan.due_date === '' ? null : loan.due_date,
            note: loan.note === '' ? null : loan.note,
        })),
        supplier_debts: data.supplier_debts.map((debt) => ({
            counterparty_id: debt.counterparty_id,
            amount: debt.amount,
        })),
        lines: data.lines.map((line) => ({
            location_id: line.location_id,
            product_id: line.product_id,
            blocks: line.blocks === '' ? 0 : Number(line.blocks),
            pieces: line.pieces === '' ? 0 : Number(line.pieces),
            price: line.price === '' ? null : line.price,
        })),
    }));

    const addProduct = (product: PickerProduct) => {
        const index = form.data.lines.findIndex(
            (line) =>
                line.product_id === product.id &&
                line.location_id === locationId,
        );

        if (index >= 0) {
            const line = form.data.lines[index];
            replaceLine(index, {
                ...line,
                pieces: String((Number(line.pieces) || 0) + 1),
            });

            return;
        }

        form.setData('lines', [
            ...form.data.lines,
            {
                key: newLineKey(),
                product_id: product.id,
                location_id: locationId,
                blocks: '',
                pieces: '1',
                price: '',
            },
        ]);
    };

    const replaceLine = (index: number, line: DraftLine) =>
        form.setData(
            'lines',
            form.data.lines.map((current, position) =>
                position === index ? line : current,
            ),
        );

    const options = { preserveScroll: true, preserveState: 'errors' as const };

    return (
        <div className="flex flex-col gap-4">
            {document && <DocumentAudit document={document} />}
            {errors.document && <AlertError errors={[errors.document]} />}

            <SectionTabs
                value={section}
                onChange={setSection}
                withErrors={sectionsWithErrors}
            />

            {section === 'cash' && (
                <CashSection
                    methods={methods}
                    payments={form.data.payments}
                    errors={errors}
                    onChange={(method, amount) =>
                        form.setData('payments', {
                            ...form.data.payments,
                            [method]: amount,
                        })
                    }
                />
            )}

            {section === 'supplier_debts' && (
                <SupplierDebtsSection
                    debts={form.data.supplier_debts}
                    counterparties={counterparties}
                    errors={errors}
                    onChange={(debts) => form.setData('supplier_debts', debts)}
                />
            )}

            {section === 'loans' && (
                <LoansSection
                    loans={form.data.loans}
                    counterparties={counterparties}
                    directions={directions}
                    errors={errors}
                    onChange={(loans) => form.setData('loans', loans)}
                />
            )}

            <div
                className={cn(
                    'flex flex-wrap items-end gap-3',
                    section !== 'stock' && 'hidden',
                )}
            >
                <div className="grid gap-2">
                    <Label htmlFor="opening-balance-location">
                        Yangi qatorlar joyi
                    </Label>
                    <select
                        id="opening-balance-location"
                        value={locationId ?? ''}
                        onChange={(event) =>
                            setLocationId(Number(event.target.value) || null)
                        }
                        className="h-9 w-56 rounded-md border border-input bg-transparent px-2 text-sm"
                    >
                        {locations.map((location) => (
                            <option key={location.id} value={location.id}>
                                {location.name}
                            </option>
                        ))}
                    </select>
                </div>
                <ProductSearch products={products} onPick={addProduct} />
            </div>

            {section !== 'stock' ? null : form.data.lines.length === 0 ? (
                <p className="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                    Mahsulotni qidiring yoki shtrix-kodini skanerlang.
                </p>
            ) : (
                <DocumentLinesTable
                    lines={form.data.lines}
                    products={productsById}
                    locations={locations}
                    priceLabel="Tannarx/dona"
                    errors={errors}
                    onChange={replaceLine}
                    onRemove={(index) =>
                        form.setData(
                            'lines',
                            form.data.lines.filter(
                                (_, position) => position !== index,
                            ),
                        )
                    }
                />
            )}

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="grid w-full max-w-md gap-2">
                    <Label htmlFor="opening-balance-note">
                        Izoh (ixtiyoriy)
                    </Label>
                    <Input
                        id="opening-balance-note"
                        value={form.data.note}
                        onChange={(event) =>
                            form.setData('note', event.target.value)
                        }
                    />
                    <InputError message={errors.note} />
                </div>
                <DocumentTotals
                    lines={form.data.lines}
                    products={productsById}
                    sumLabel="Tovarlar qiymati"
                />
            </div>

            <div className="flex flex-wrap items-center justify-between gap-4">
                <DocumentFormActions
                    processing={form.processing}
                    cancelHref={dashboard.url()}
                    executeDescription={EXECUTE_WARNING}
                    onSave={() =>
                        form.put(OpeningBalanceController.update.url(), options)
                    }
                    onExecute={() =>
                        form.post(
                            OpeningBalanceController.execute.url(),
                            options,
                        )
                    }
                    onDelete={
                        document
                            ? () =>
                                  router.delete(
                                      OpeningBalanceController.destroy.url(),
                                  )
                            : undefined
                    }
                />
                <SkipOpeningBalance />
            </div>
        </div>
    );
}

/** Opening cash per payment method; empty means none. */
function CashSection({
    methods,
    payments,
    errors,
    onChange,
}: {
    methods: EnumOption[];
    payments: Record<string, string>;
    errors: FieldErrors;
    onChange: (method: string, amount: string) => void;
}) {
    const total = Object.values(payments).reduce(
        (sum, amount) => sum + (Number(amount) || 0),
        0,
    );

    return (
        <div className="grid max-w-md gap-4">
            {methods.map((method) => (
                <div key={method.id} className="grid gap-2">
                    <Label htmlFor={`opening-cash-${method.id}`}>
                        {method.name}
                    </Label>
                    <Input
                        id={`opening-cash-${method.id}`}
                        inputMode="decimal"
                        placeholder="0"
                        value={payments[method.id] ?? ''}
                        onChange={(event) =>
                            onChange(method.id, event.target.value)
                        }
                    />
                    <InputError message={errors[`payments.${method.id}`]} />
                </div>
            ))}
            <p className="text-sm">
                Jami:{' '}
                <span className="font-semibold">{formatMoney(total)}</span>
            </p>
        </div>
    );
}

/** What the store already owed each supplier; one row per supplier. */
function SupplierDebtsSection({
    debts,
    counterparties,
    errors,
    onChange,
}: {
    debts: DraftDebt[];
    counterparties: PickerOption[];
    errors: FieldErrors;
    onChange: (debts: DraftDebt[]) => void;
}) {
    const update = (index: number, changes: Partial<DraftDebt>) =>
        onChange(
            debts.map((debt, position) =>
                position === index ? { ...debt, ...changes } : debt,
            ),
        );
    const add = (counterpartyId: number | '' = '') =>
        onChange([
            ...debts,
            { key: newLineKey(), counterparty_id: counterpartyId, amount: '' },
        ]);
    const total = debts.reduce(
        (sum, debt) => sum + (Number(debt.amount) || 0),
        0,
    );

    return (
        <div className="flex flex-col gap-4">
            {debts.length > 0 && (
                <ListTable columns={['Yetkazib beruvchi', 'Qarz summasi', '']}>
                    {debts.map((debt, index) => (
                        <tr key={debt.key} className="border-t align-top">
                            <td className="px-4 py-3">
                                <select
                                    aria-label="Yetkazib beruvchi"
                                    value={debt.counterparty_id}
                                    onChange={(event) =>
                                        update(index, {
                                            counterparty_id:
                                                Number(event.target.value) ||
                                                '',
                                        })
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
                                <InputError
                                    message={
                                        errors[
                                            `supplier_debts.${index}.counterparty_id`
                                        ]
                                    }
                                />
                            </td>
                            <td className="px-4 py-3">
                                <Input
                                    aria-label="Qarz summasi"
                                    inputMode="decimal"
                                    value={debt.amount}
                                    onChange={(event) =>
                                        update(index, {
                                            amount: event.target.value,
                                        })
                                    }
                                />
                                <InputError
                                    message={
                                        errors[`supplier_debts.${index}.amount`]
                                    }
                                />
                            </td>
                            <td className="px-4 py-3">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Qatorni o'chirish"
                                    onClick={() =>
                                        onChange(
                                            debts.filter(
                                                (_, position) =>
                                                    position !== index,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 />
                                </Button>
                            </td>
                        </tr>
                    ))}
                </ListTable>
            )}
            <p className="text-sm">
                Jami:{' '}
                <span className="font-semibold">{formatMoney(total)}</span>
            </p>
            <div className="flex gap-2">
                <Button type="button" variant="outline" onClick={() => add()}>
                    <Plus />
                    Qarz qo'shish
                </Button>
                <CounterpartyQuickCreate
                    trigger={
                        <Button type="button" variant="ghost">
                            <Plus />
                            Yangi yetkazib beruvchi
                        </Button>
                    }
                    onCreated={(counterparty) => add(counterparty.id)}
                />
            </div>
        </div>
    );
}

/** Loans already running: given or received, counterparty, principal, optional due date. */
function LoansSection({
    loans,
    counterparties,
    directions,
    errors,
    onChange,
}: {
    loans: DraftLoan[];
    counterparties: PickerOption[];
    directions: EnumOption[];
    errors: FieldErrors;
    onChange: (loans: DraftLoan[]) => void;
}) {
    const update = (index: number, changes: Partial<DraftLoan>) =>
        onChange(
            loans.map((loan, position) =>
                position === index ? { ...loan, ...changes } : loan,
            ),
        );
    const add = (counterpartyId: number | '' = '') =>
        onChange([
            ...loans,
            {
                key: newLineKey(),
                direction: directions[0]?.id ?? 'given',
                counterparty_id: counterpartyId,
                principal: '',
                due_date: '',
                note: '',
            },
        ]);

    return (
        <div className="flex flex-col gap-4">
            {loans.length > 0 && (
                <ListTable
                    columns={[
                        'Turi',
                        'Kontragent',
                        'Asosiy summa',
                        'Muddat (ixtiyoriy)',
                        'Izoh',
                        '',
                    ]}
                >
                    {loans.map((loan, index) => (
                        <tr key={loan.key} className="border-t align-top">
                            <td className="px-4 py-3">
                                <select
                                    aria-label="Turi"
                                    value={loan.direction}
                                    onChange={(event) =>
                                        update(index, {
                                            direction: event.target.value,
                                        })
                                    }
                                    className={selectClassName}
                                >
                                    {directions.map((direction) => (
                                        <option
                                            key={direction.id}
                                            value={direction.id}
                                        >
                                            {direction.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={errors[`loans.${index}.direction`]}
                                />
                            </td>
                            <td className="px-4 py-3">
                                <select
                                    aria-label="Kontragent"
                                    value={loan.counterparty_id}
                                    onChange={(event) =>
                                        update(index, {
                                            counterparty_id:
                                                Number(event.target.value) ||
                                                '',
                                        })
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
                                <InputError
                                    message={
                                        errors[`loans.${index}.counterparty_id`]
                                    }
                                />
                            </td>
                            <td className="px-4 py-3">
                                <Input
                                    aria-label="Asosiy summa"
                                    inputMode="decimal"
                                    value={loan.principal}
                                    onChange={(event) =>
                                        update(index, {
                                            principal: event.target.value,
                                        })
                                    }
                                />
                                <InputError
                                    message={errors[`loans.${index}.principal`]}
                                />
                            </td>
                            <td className="px-4 py-3">
                                <Input
                                    aria-label="Muddat"
                                    type="date"
                                    value={loan.due_date}
                                    onChange={(event) =>
                                        update(index, {
                                            due_date: event.target.value,
                                        })
                                    }
                                />
                                <InputError
                                    message={errors[`loans.${index}.due_date`]}
                                />
                            </td>
                            <td className="px-4 py-3">
                                <Input
                                    aria-label="Izoh"
                                    value={loan.note}
                                    onChange={(event) =>
                                        update(index, {
                                            note: event.target.value,
                                        })
                                    }
                                />
                            </td>
                            <td className="px-4 py-3">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Qatorni o'chirish"
                                    onClick={() =>
                                        onChange(
                                            loans.filter(
                                                (_, position) =>
                                                    position !== index,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 />
                                </Button>
                            </td>
                        </tr>
                    ))}
                </ListTable>
            )}
            <div className="flex gap-2">
                <Button type="button" variant="outline" onClick={() => add()}>
                    <Plus />
                    Qarz qo'shish
                </Button>
                <CounterpartyQuickCreate
                    trigger={
                        <Button type="button" variant="ghost">
                            <Plus />
                            Yangi kontragent
                        </Button>
                    }
                    onCreated={(counterparty) => add(counterparty.id)}
                />
            </div>
        </div>
    );
}

OpeningBalanceShow.layout = {
    breadcrumbs: [
        {
            title: "Boshlang'ich qoldiq",
            href: show(),
        },
    ],
};
