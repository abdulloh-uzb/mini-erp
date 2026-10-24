import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus, Undo2 } from 'lucide-react';
import { useMemo } from 'react';
import PurchaseController from '@/actions/App/Http/Controllers/PurchaseController';
import SupplierReturnController from '@/actions/App/Http/Controllers/SupplierReturnController';
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
    linePieces,
    newLineKey,
    ProductSearch,
} from '@/components/document-form';
import type { DraftLine, LineErrors } from '@/components/document-form';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { MoneyInput } from '@/components/money-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import { formatMoney, splitQuantity } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index } from '@/routes/purchases';
import type {
    DocumentData,
    EnumOption,
    PickerOption,
    PickerProduct,
} from '@/types';

type Props = {
    document: DocumentData | null;
    editable: boolean;
    locations: PickerOption[];
    products: PickerProduct[];
    counterparties: PickerOption[];
    methods: EnumOption[];
    /** Previous purchase price per product id; only for users who may view costs. */
    previous_prices?: Record<number, string>;
    /** What the store owes each supplier, per counterparty id; only for users who may view costs. */
    supplier_debts?: Record<number, string>;
};

type FieldErrors = Record<string, string | undefined>;

export default function PurchaseShow({
    document,
    editable,
    locations,
    products,
    counterparties,
    methods,
    previous_prices: previousPrices,
    supplier_debts: supplierDebts,
}: Props) {
    const title = document ? `Xarid №${document.number}` : 'Yangi xarid';

    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={title}
                        description="Yetkazib beruvchidan kelgan tovarlar: omborga kiradi, to'lanmagan qismi yetkazib beruvchiga qarz bo'ladi."
                    />
                    {document && (
                        <DocumentStatusBadge status={document.status} />
                    )}
                </div>

                {editable ? (
                    <PurchaseForm
                        document={document}
                        locations={locations}
                        products={products}
                        counterparties={counterparties}
                        methods={methods}
                        previousPrices={previousPrices}
                        supplierDebts={supplierDebts}
                    />
                ) : (
                    document && (
                        <ReadOnlyPurchase
                            document={document}
                            methods={methods}
                        />
                    )
                )}
            </div>
        </>
    );
}

function ReadOnlyPurchase({
    document,
    methods,
}: {
    document: DocumentData;
    methods: EnumOption[];
}) {
    const { can } = usePermissions();
    const showPrices = document.payments !== undefined;
    const total = document.lines.reduce(
        (sum, line) => sum + line.quantity * Number(line.price ?? 0),
        0,
    );

    return (
        <div className="flex flex-col gap-4">
            <DocumentAudit document={document} />
            <dl className="grid max-w-xl grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                <dt className="text-muted-foreground">Yetkazib beruvchi</dt>
                <dd>{document.counterparty_name ?? '—'}</dd>
                <dt className="text-muted-foreground">Joy</dt>
                <dd>{document.location_name ?? '—'}</dd>
                {document.note && (
                    <>
                        <dt className="text-muted-foreground">Izoh</dt>
                        <dd>{document.note}</dd>
                    </>
                )}
            </dl>
            <ExecutedLinesTable document={document} showPrice={showPrices} />
            {showPrices && (
                <PaymentSummary
                    total={total}
                    payments={methods.map((method) => ({
                        label: method.name,
                        amount: Number(document.payments?.[method.id] ?? 0),
                    }))}
                />
            )}
            {document.status === 'draft' && (
                <p className="text-sm text-muted-foreground">
                    Egasi bajarishini kutmoqda.
                </p>
            )}
            {document.status === 'executed' &&
                can('supplier-return.manage') && (
                    <div>
                        <Button variant="outline" asChild>
                            <Link
                                href={SupplierReturnController.create({
                                    query: { source: document.id },
                                })}
                            >
                                <Undo2 /> Qaytarish yaratish
                            </Link>
                        </Button>
                    </div>
                )}
        </div>
    );
}

/** Jami, each method paid, To'langan and Qarzga qoladi (the supplier debt part). */
function PaymentSummary({
    total,
    payments,
}: {
    total: number;
    payments: { label: string; amount: number }[];
}) {
    const paid = payments.reduce((sum, payment) => sum + payment.amount, 0);

    return (
        <dl className="grid w-full max-w-xs gap-2 rounded-xl border p-4 text-sm">
            <SummaryRow label="Jami" value={formatMoney(total)} strong />
            {payments
                .filter((payment) => payment.amount > 0)
                .map((payment) => (
                    <SummaryRow
                        key={payment.label}
                        label={payment.label}
                        value={formatMoney(payment.amount)}
                    />
                ))}
            <SummaryRow label="To'langan" value={formatMoney(paid)} />
            <SummaryRow
                label="Qarzga qoldi"
                value={formatMoney(Math.max(total - paid, 0))}
                strong
            />
        </dl>
    );
}

function SummaryRow({
    label,
    value,
    strong = false,
}: {
    label: string;
    value: string;
    strong?: boolean;
}) {
    return (
        <div className="flex justify-between gap-4">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className={cn('tabular-nums', strong && 'font-semibold')}>
                {value}
            </dd>
        </div>
    );
}

function PurchaseForm({
    document,
    locations,
    products,
    counterparties,
    methods,
    previousPrices,
    supplierDebts,
}: {
    document: DocumentData | null;
    locations: PickerOption[];
    products: PickerProduct[];
    counterparties: PickerOption[];
    methods: EnumOption[];
    previousPrices?: Record<number, string>;
    supplierDebts?: Record<number, string>;
}) {
    const { can } = usePermissions();
    const showPrices = previousPrices !== undefined;
    const canExecute = can('purchase.execute');
    const productsById = useMemo(
        () => new Map(products.map((product) => [product.id, product])),
        [products],
    );
    const previousPrice = (productId: number) =>
        previousPrices?.[productId] ?? '';

    const form = useForm({
        counterparty_id: document?.counterparty_id ?? ('' as number | ''),
        location_id: (document?.location_id ?? locations.at(0)?.id ?? '') as
            number | '',
        note: document?.note ?? '',
        lines: (document?.lines ?? []).map((line): DraftLine => {
            const split = splitQuantity(line.quantity, line.pieces_per_block);

            return {
                key: newLineKey(),
                product_id: line.product_id,
                location_id: null,
                blocks: String(split.blocks),
                pieces: String(split.pieces),
                price: line.price ?? previousPrice(line.product_id),
            };
        }),
        payments: Object.fromEntries(
            methods.map((method) => [
                method.id,
                document?.payments?.[method.id] ?? '',
            ]),
        ) as Record<string, string>,
    });
    const errors = form.errors as LineErrors & FieldErrors;

    const addProduct = (product: PickerProduct) => {
        const index = form.data.lines.findIndex(
            (line) => line.product_id === product.id,
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
                location_id: null,
                blocks: '',
                pieces: '1',
                price: previousPrice(product.id),
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

    const submit = (execute: boolean) => {
        form.transform((data) => ({
            counterparty_id: data.counterparty_id,
            location_id: data.location_id,
            note: data.note === '' ? null : data.note,
            lines: data.lines.map((line) => ({
                product_id: line.product_id,
                blocks: line.blocks === '' ? 0 : Number(line.blocks),
                pieces: line.pieces === '' ? 0 : Number(line.pieces),
                ...(showPrices
                    ? { price: line.price === '' ? null : line.price }
                    : {}),
            })),
            ...(showPrices
                ? {
                      payments: Object.fromEntries(
                          Object.entries(data.payments).map(
                              ([method, amount]) => [
                                  method,
                                  amount === '' ? null : amount,
                              ],
                          ),
                      ),
                  }
                : {}),
            execute,
        }));

        const options = {
            preserveScroll: true,
            preserveState: 'errors' as const,
        };

        if (document) {
            form.put(PurchaseController.update.url(document.id), options);
        } else {
            form.post(PurchaseController.store.url(), options);
        }
    };

    const total = form.data.lines.reduce(
        (sum, line) =>
            sum +
            linePieces(
                line,
                productsById.get(line.product_id)?.pieces_per_block ?? 1,
            ) *
                (Number(line.price) || 0),
        0,
    );
    const supplierDebt =
        form.data.counterparty_id === ''
            ? null
            : (supplierDebts?.[form.data.counterparty_id] ?? '0');

    return (
        <div className="flex flex-col gap-4">
            {document && <DocumentAudit document={document} />}
            {errors.document && <AlertError errors={[errors.document]} />}

            <div className="flex flex-wrap items-end gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="purchase-supplier">Yetkazib beruvchi</Label>
                    <div className="flex gap-2">
                        <select
                            id="purchase-supplier"
                            value={form.data.counterparty_id}
                            onChange={(event) =>
                                form.setData(
                                    'counterparty_id',
                                    Number(event.target.value) || '',
                                )
                            }
                            className={cn(selectClassName, 'w-64')}
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
                            title="Yangi yetkazib beruvchi"
                            trigger={
                                <Button type="button" variant="outline">
                                    <Plus />
                                    Yangi
                                </Button>
                            }
                            onCreated={(counterparty) =>
                                form.setData('counterparty_id', counterparty.id)
                            }
                        />
                    </div>
                    <InputError message={errors.counterparty_id} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="purchase-location">Joy</Label>
                    <select
                        id="purchase-location"
                        value={form.data.location_id}
                        onChange={(event) =>
                            form.setData(
                                'location_id',
                                Number(event.target.value) || '',
                            )
                        }
                        className={cn(selectClassName, 'w-56')}
                    >
                        {locations.map((location) => (
                            <option key={location.id} value={location.id}>
                                {location.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.location_id} />
                </div>
                {supplierDebt !== null && (
                    <p className="pb-2 text-sm text-muted-foreground">
                        Joriy qarz:{' '}
                        <span className="font-medium text-foreground tabular-nums">
                            {formatMoney(supplierDebt)}
                        </span>
                    </p>
                )}
            </div>

            <ProductSearch products={products} onPick={addProduct} />
            <InputError message={errors.lines} />

            {form.data.lines.length === 0 ? (
                <p className="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                    Mahsulotni qidiring yoki shtrix-kodini skanerlang.
                </p>
            ) : (
                <DocumentLinesTable
                    lines={form.data.lines}
                    products={productsById}
                    showPrice={showPrices}
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
                    <Label htmlFor="purchase-note">Izoh (ixtiyoriy)</Label>
                    <Input
                        id="purchase-note"
                        placeholder="Faktura raqami va boshqalar"
                        value={form.data.note}
                        onChange={(event) =>
                            form.setData('note', event.target.value)
                        }
                    />
                    <InputError message={errors.note} />
                </div>
                {showPrices ? (
                    <PaymentPanel
                        methods={methods}
                        payments={form.data.payments}
                        total={total}
                        errors={errors}
                        onChange={(method, amount) =>
                            form.setData('payments', {
                                ...form.data.payments,
                                [method]: amount,
                            })
                        }
                    />
                ) : (
                    <DocumentTotals
                        lines={form.data.lines}
                        products={productsById}
                        showPrice={false}
                    />
                )}
            </div>

            {!canExecute && document && (
                <p className="text-sm text-muted-foreground">
                    Egasi bajarishini kutmoqda.
                </p>
            )}

            <DocumentFormActions
                processing={form.processing}
                cancelHref={index.url()}
                onSave={() => submit(false)}
                onExecute={canExecute ? () => submit(true) : undefined}
                onDelete={
                    document
                        ? () =>
                              router.delete(
                                  PurchaseController.destroy.url(document.id),
                              )
                        : undefined
                }
            />
        </div>
    );
}

/**
 * Payment per method, with Jami, To'langan and the live Qarzga qoladi. Qolganini fills a
 * method with what is still unpaid; paying more than Jami warns, since execution refuses it.
 */
function PaymentPanel({
    methods,
    payments,
    total,
    errors,
    onChange,
}: {
    methods: EnumOption[];
    payments: Record<string, string>;
    total: number;
    errors: FieldErrors;
    onChange: (method: string, amount: string) => void;
}) {
    const paid = Object.values(payments).reduce(
        (sum, amount) => sum + (Number(amount) || 0),
        0,
    );
    const overpaid = paid - total;
    const payRest = (method: string) =>
        onChange(
            method,
            String(
                Math.round(
                    Math.max(
                        total - paid + (Number(payments[method]) || 0),
                        0,
                    ) * 100,
                ) / 100,
            ),
        );

    return (
        <div className="grid w-full max-w-xs gap-3 rounded-xl border p-4 text-sm">
            <SummaryRow label="Jami" value={formatMoney(total)} strong />
            {methods.map((method) => (
                <div key={method.id} className="grid gap-1">
                    <Label htmlFor={`purchase-payment-${method.id}`}>
                        {method.name}
                    </Label>
                    <div className="flex gap-2">
                        <MoneyInput
                            id={`purchase-payment-${method.id}`}
                            placeholder="0"
                            value={payments[method.id] ?? ''}
                            onValueChange={(amount) =>
                                onChange(method.id, amount)
                            }
                        />
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => payRest(method.id)}
                        >
                            Qolganini
                        </Button>
                    </div>
                    <InputError message={errors[`payments.${method.id}`]} />
                </div>
            ))}
            <InputError message={errors.payments} />
            <SummaryRow label="To'langan" value={formatMoney(paid)} />
            {overpaid > 0.004 && (
                <p className="text-destructive">
                    To'lov jamidan {formatMoney(overpaid)} ko'p
                </p>
            )}
            <SummaryRow
                label="Qarzga qoladi"
                value={formatMoney(Math.max(total - paid, 0))}
                strong
            />
        </div>
    );
}

PurchaseShow.layout = {
    breadcrumbs: [
        {
            title: 'Xaridlar',
            href: index(),
        },
    ],
};
