import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Minus,
    Plus,
    Printer,
    Search,
    Trash2,
    UserPlus,
    X,
} from 'lucide-react';
import type { RefObject } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';
import SaleController from '@/actions/App/Http/Controllers/SaleController';
import { selectClassName } from '@/components/cash-form';
import CounterpartyQuickCreate from '@/components/counterparty-quick-create';
import {
    ConfirmDialog,
    EXECUTE_WARNING,
    linePieces,
    newLineKey,
} from '@/components/document-form';
import type { DraftLine, LineErrors } from '@/components/document-form';
import InputError from '@/components/input-error';
import SaleReceipt from '@/components/sale-receipt';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/hooks/use-permissions';
import { formatMoney, formatQuantity, splitQuantity } from '@/lib/format';
import { cn } from '@/lib/utils';
import { create } from '@/routes/sales';
import type {
    DocumentData,
    EnumOption,
    ParkedSale,
    PickerOption,
    PickerProduct,
    SaleCustomer,
    SaleReceipt as SaleReceiptData,
    StockRow,
} from '@/types';

type Props = {
    /** The parked sale reopened on the screen; null for a fresh sale. */
    document: DocumentData | null;
    default_location_id: number | null;
    locations: PickerOption[];
    products: PickerProduct[];
    stock: StockRow[];
    customers: SaleCustomer[];
    /** What each customer owes the store, per counterparty id. */
    customer_debts: Record<number, string>;
    methods: EnumOption[];
    /** The last executed sale price per product id. */
    previous_prices: Record<number, string>;
    /** Other parked sales (Qoralamalar). */
    drafts: ParkedSale[];
    /** Average cost per product id; only for users who may view costs. */
    average_costs?: Record<number, string>;
};

type FieldErrors = Record<string, string | undefined>;

/** An amount as the server takes it: rounded to whole tiyin, no float tails. */
function toMoney(amount: number): string {
    return String(Math.round(amount * 100) / 100);
}

/**
 * The sale screen (D1, prototype variant B): product tiles and the scan box on the left, a
 * receipt-style cart on the right with parked sales as tabs and the payment at the bottom.
 * F2 parks the sale, F9 executes it after a confirmation.
 */
export default function SaleCreate(props: Props) {
    const { document } = props;

    return (
        <>
            <Head title={document ? `Sotuv №${document.number}` : 'Sotuv'} />
            <SaleScreen key={document?.id ?? 'new'} {...props} />
        </>
    );
}

function SaleScreen({
    document,
    default_location_id: defaultLocationId,
    locations,
    products,
    stock,
    customers,
    customer_debts: customerDebts,
    methods,
    previous_prices: previousPrices,
    drafts,
    average_costs: averageCosts,
}: Props) {
    const { can } = usePermissions();
    const canExecute = can('sale.execute');
    const scanRef = useRef<HTMLInputElement>(null);
    const [confirming, setConfirming] = useState(false);
    const [method, setMethod] = useState(methods[0]?.id ?? 'cash');
    const [received, setReceived] = useState('');
    const [lastLineKey, setLastLineKey] = useState<string | null>(null);
    const productsById = useMemo(
        () => new Map(products.map((product) => [product.id, product])),
        [products],
    );

    const form = useForm({
        counterparty_id: (document?.counterparty_id ?? null) as number | null,
        location_id: (document?.location_id ?? defaultLocationId ?? '') as
            | number
            | '',
        lines: (document?.lines ?? []).map((line): DraftLine => {
            const split = splitQuantity(line.quantity, line.pieces_per_block);

            return {
                key: newLineKey(),
                product_id: line.product_id,
                location_id: null,
                blocks: split.blocks ? String(split.blocks) : '',
                pieces: String(split.pieces),
                price: line.price ?? '',
            };
        }),
        payments: Object.fromEntries(
            methods.map((item) => [
                item.id,
                document?.payments?.[item.id] ?? '',
            ]),
        ) as Record<string, string>,
    });
    /** The page's errors, not the form's: a refused F9 on a new sale lands on the parked sale's URL and remounts the screen. */
    const errors = usePage().props.errors as LineErrors & FieldErrors;

    const piecesPerBlock = (productId: number) =>
        productsById.get(productId)?.pieces_per_block ?? 1;
    const stockOf = (productId: number) =>
        stock.find(
            (row) =>
                row.product_id === productId &&
                row.location_id === form.data.location_id,
        )?.quantity ?? 0;
    const lineQuantity = (line: DraftLine) =>
        linePieces(line, piecesPerBlock(line.product_id));
    const lineSum = (line: DraftLine) =>
        lineQuantity(line) * (Number(line.price) || 0);

    const total = form.data.lines.reduce((sum, line) => sum + lineSum(line), 0);
    const paid = Object.values(form.data.payments).reduce(
        (sum, amount) => sum + (Number(amount) || 0),
        0,
    );
    const debt = Math.max(total - paid, 0);
    const customer =
        customers.find((item) => item.id === form.data.counterparty_id) ?? null;

    const setQuantity = (index: number, pieces: number) => {
        const line = form.data.lines[index];

        if (pieces <= 0) {
            form.setData(
                'lines',
                form.data.lines.filter((_, position) => position !== index),
            );

            return;
        }

        const split = splitQuantity(pieces, piecesPerBlock(line.product_id));
        replaceLine(index, {
            ...line,
            blocks: split.blocks ? String(split.blocks) : '',
            pieces: String(split.pieces),
        });
    };

    const replaceLine = (index: number, line: DraftLine) =>
        form.setData(
            'lines',
            form.data.lines.map((current, position) =>
                position === index ? line : current,
            ),
        );

    /** A scan, Enter or a tile adds +1 dona, merging into the product's line. */
    const addProduct = (product: PickerProduct) => {
        const index = form.data.lines.findIndex(
            (line) => line.product_id === product.id,
        );

        if (index >= 0) {
            setQuantity(index, lineQuantity(form.data.lines[index]) + 1);
            setLastLineKey(form.data.lines[index].key);
        } else {
            const key = newLineKey();
            setLastLineKey(key);
            form.setData('lines', [
                ...form.data.lines,
                {
                    key,
                    product_id: product.id,
                    location_id: null,
                    blocks: '',
                    pieces: '1',
                    price: previousPrices[product.id] ?? '',
                },
            ]);
        }

        scanRef.current?.focus();
    };

    const setPayment = (methodId: string, amount: string) =>
        form.setData('payments', { ...form.data.payments, [methodId]: amount });
    const payAllCash = () => {
        form.setData(
            'payments',
            Object.fromEntries(
                methods.map((item) => [
                    item.id,
                    item.id === 'cash' ? toMoney(total) : '',
                ]),
            ),
        );
        setMethod('cash');
    };
    const payRest = () =>
        setPayment(
            method,
            toMoney(
                Math.max(
                    total - paid + (Number(form.data.payments[method]) || 0),
                    0,
                ),
            ),
        );

    const submit = (execute: boolean, onSuccess?: () => void) => {
        form.transform((data) => ({
            counterparty_id: data.counterparty_id,
            location_id: data.location_id,
            lines: data.lines.map((line) => ({
                product_id: line.product_id,
                blocks: line.blocks === '' ? 0 : Number(line.blocks),
                pieces: line.pieces === '' ? 0 : Number(line.pieces),
                price: line.price === '' ? null : line.price,
            })),
            payments: Object.fromEntries(
                Object.entries(data.payments).map(([key, amount]) => [
                    key,
                    amount === '' ? null : amount,
                ]),
            ),
            execute,
        }));

        const options = {
            preserveScroll: true,
            preserveState: 'errors' as const,
            onSuccess,
        };

        if (document) {
            form.put(SaleController.update.url(document.id), options);
        } else {
            form.post(SaleController.store.url(), options);
        }
    };

    /** Park the current sale (when it has lines) before opening another one. */
    const openParked = (id: number) => {
        const visit = () => router.visit(SaleController.show.url(id));

        if (form.data.lines.length > 0) {
            submit(false, visit);
        } else {
            visit();
        }
    };

    const park = () => {
        if (form.data.lines.length > 0 && !form.processing) {
            submit(false);
        }
    };

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (event.key !== 'F2' && event.key !== 'F9') {
                return;
            }

            event.preventDefault();

            if (confirming) {
                return;
            }

            if (event.key === 'F2') {
                park();
            } else if (canExecute && form.data.lines.length > 0) {
                setConfirming(true);
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    });

    const lineErrors = Object.entries(errors).filter(([key]) =>
        key.startsWith('lines'),
    ).length;

    return (
        <div className="grid flex-1 lg:grid-cols-[1fr_26rem]">
            <ProductPanel
                products={products}
                previousPrices={previousPrices}
                lines={form.data.lines}
                scanRef={scanRef}
                onTab={() => {
                    const input =
                        window.document.querySelector<HTMLInputElement>(
                            `[data-blocks-input="${lastLineKey}"]`,
                        );
                    input?.focus();
                    input?.select();

                    return input !== null;
                }}
                stockOf={stockOf}
                piecesPerBlock={piecesPerBlock}
                lineQuantity={lineQuantity}
                onPick={addProduct}
            />

            <aside className="flex flex-col border-t bg-muted/30 lg:border-t-0 lg:border-l">
                <div className="flex items-center gap-1 overflow-x-auto border-b px-2 pt-2">
                    <span className="rounded-t-md border border-b-0 bg-background px-3 py-1.5 text-sm font-medium whitespace-nowrap">
                        {document
                            ? `Qoralama №${document.number}`
                            : 'Joriy sotuv'}
                    </span>
                    {document && (
                        <button
                            type="button"
                            onClick={() => router.visit(create())}
                            className="rounded-t-md px-3 py-1.5 text-sm whitespace-nowrap text-muted-foreground hover:bg-background"
                        >
                            + Yangi
                        </button>
                    )}
                    {drafts.map((draft) => (
                        <button
                            key={draft.id}
                            type="button"
                            onClick={() => openParked(draft.id)}
                            disabled={form.processing}
                            className="rounded-t-md px-3 py-1.5 text-sm whitespace-nowrap text-muted-foreground hover:bg-background"
                        >
                            {draft.counterparty ?? `№${draft.number}`} ·{' '}
                            {formatMoney(draft.total)}
                        </button>
                    ))}
                    <span className="ml-auto px-2 text-xs whitespace-nowrap text-muted-foreground">
                        Qoralamalar ({drafts.length})
                    </span>
                </div>

                <div className="grid gap-2 border-b p-3 sm:grid-cols-2">
                    <div>
                        <select
                            value={form.data.location_id}
                            onChange={(event) =>
                                form.setData(
                                    'location_id',
                                    Number(event.target.value) || '',
                                )
                            }
                            aria-label="Joy"
                            className={selectClassName}
                        >
                            {locations.map((location) => (
                                <option key={location.id} value={location.id}>
                                    {location.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.location_id} />
                    </div>
                    <div>
                        <CustomerPicker
                            customers={customers}
                            customerDebts={customerDebts}
                            customer={customer}
                            required={debt > 0}
                            onChange={(id) =>
                                form.setData('counterparty_id', id)
                            }
                        />
                        <InputError message={errors.counterparty_id} />
                    </div>
                </div>

                <ul className="flex-1 divide-y overflow-y-auto">
                    {form.data.lines.length === 0 && (
                        <li className="p-8 text-center text-sm text-muted-foreground">
                            Shtrix-kodni skanerlang yoki mahsulotni tanlang
                        </li>
                    )}
                    {form.data.lines.map((line, index) => (
                        <CartLine
                            key={line.key}
                            line={line}
                            product={productsById.get(line.product_id)}
                            quantity={lineQuantity(line)}
                            sum={lineSum(line)}
                            stock={stockOf(line.product_id)}
                            averageCost={averageCosts?.[line.product_id]}
                            error={(field) => errors[`lines.${index}.${field}`]}
                            onQuantity={(pieces) => setQuantity(index, pieces)}
                            onLine={(changed) => replaceLine(index, changed)}
                            onRemove={() => setQuantity(index, 0)}
                        />
                    ))}
                </ul>

                <div className="grid gap-3 border-t bg-background p-3">
                    {(errors.document || lineErrors > 0) && (
                        <p className="text-sm text-red-600">
                            {errors.document ??
                                "Qatorlardagi xatolarni to'g'rilang."}
                        </p>
                    )}
                    <InputError message={errors.lines} />
                    <div className="flex items-baseline justify-between">
                        <span className="text-muted-foreground">Jami</span>
                        <span className="text-3xl font-bold tabular-nums">
                            {formatMoney(total)}
                        </span>
                    </div>
                    <div className="grid grid-cols-3 gap-1">
                        {methods.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => setMethod(item.id)}
                                className={cn(
                                    'rounded-md border px-2 py-1.5 text-xs',
                                    Number(form.data.payments[item.id]) > 0 &&
                                        'border-primary',
                                    method === item.id &&
                                        'border-primary bg-primary text-primary-foreground',
                                )}
                            >
                                {item.name}
                                {Number(form.data.payments[item.id]) > 0 && (
                                    <div className="tabular-nums">
                                        {formatMoney(
                                            form.data.payments[item.id],
                                        )}
                                    </div>
                                )}
                            </button>
                        ))}
                    </div>
                    <div className="flex gap-2">
                        <Input
                            type="number"
                            min={0}
                            step="0.01"
                            value={form.data.payments[method] ?? ''}
                            onChange={(event) =>
                                setPayment(method, event.target.value)
                            }
                            className="h-11 text-right text-lg tabular-nums"
                            aria-label={`${methods.find((item) => item.id === method)?.name ?? ''} summasi`}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            className="h-11"
                            onClick={payRest}
                        >
                            Qolganini
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            className="h-11"
                            onClick={payAllCash}
                        >
                            To'liq naqd
                        </Button>
                    </div>
                    <InputError
                        message={
                            errors.payments ??
                            methods
                                .map((item) => errors[`payments.${item.id}`])
                                .find(Boolean)
                        }
                    />
                    {paid > total && (
                        <p className="text-sm text-red-600">
                            To'lov jami summadan oshib ketdi.
                        </p>
                    )}
                    <div className="flex justify-between text-sm">
                        <span className="font-medium">Qarzga qoladi</span>
                        <span
                            className={cn(
                                'font-semibold tabular-nums',
                                debt > 0 && 'text-red-600',
                            )}
                        >
                            {formatMoney(debt)}
                        </span>
                    </div>
                    {method === 'cash' && (
                        <ChangeHelper
                            received={received}
                            onReceived={setReceived}
                            cashDue={
                                Number(form.data.payments.cash) ||
                                Math.max(
                                    total -
                                        paid +
                                        (Number(form.data.payments.cash) || 0),
                                    0,
                                )
                            }
                        />
                    )}
                    <div className="grid grid-cols-[auto_1fr] gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-12"
                            onClick={park}
                            disabled={
                                form.processing || form.data.lines.length === 0
                            }
                        >
                            Saqlash (F2)
                        </Button>
                        {canExecute && (
                            <Button
                                type="button"
                                className="h-12 bg-green-600 text-lg hover:bg-green-700"
                                onClick={() => setConfirming(true)}
                                disabled={form.processing}
                            >
                                Bajarish (F9)
                            </Button>
                        )}
                    </div>
                    {document && (
                        <ConfirmDialog
                            trigger={
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="justify-self-start text-muted-foreground"
                                    disabled={form.processing}
                                >
                                    <Trash2 /> Qoralamani o'chirish
                                </Button>
                            }
                            title="Qoralamani o'chirish"
                            description="Qoralama butunlay o'chiriladi. Davom etasizmi?"
                            confirmLabel="O'chirish"
                            destructive
                            onConfirm={() =>
                                router.delete(
                                    SaleController.destroy.url(document.id),
                                )
                            }
                        />
                    )}
                </div>
            </aside>

            <ExecuteDialog
                open={confirming}
                onOpenChange={setConfirming}
                total={total}
                debt={debt}
                onConfirm={() => submit(true)}
            />
            <FlashedReceipt />
        </div>
    );
}

function ProductPanel({
    products,
    previousPrices,
    lines,
    scanRef,
    onTab,
    stockOf,
    piecesPerBlock,
    lineQuantity,
    onPick,
}: {
    products: PickerProduct[];
    previousPrices: Record<number, string>;
    lines: DraftLine[];
    scanRef: RefObject<HTMLInputElement | null>;
    /** Tab from the scan box: focus the last scanned line's Blok; false when there is none. */
    onTab: () => boolean;
    stockOf: (productId: number) => number;
    piecesPerBlock: (productId: number) => number;
    lineQuantity: (line: DraftLine) => number;
    onPick: (product: PickerProduct) => void;
}) {
    const [query, setQuery] = useState('');
    const term = query.trim().toLowerCase();
    const matches = useMemo(
        () =>
            term === ''
                ? products
                : products.filter(
                      (product) =>
                          product.name.toLowerCase().includes(term) ||
                          product.barcode?.includes(term),
                  ),
        [products, term],
    );

    return (
        <section className="flex min-w-0 flex-col gap-3 p-4">
            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-muted-foreground" />
                <Input
                    ref={scanRef}
                    autoFocus
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Tab' && !event.shiftKey && onTab()) {
                            event.preventDefault();

                            return;
                        }

                        if (event.key !== 'Enter') {
                            return;
                        }

                        event.preventDefault();
                        const product =
                            products.find(
                                (item) => item.barcode === query.trim(),
                            ) ?? (term === '' ? undefined : matches[0]);

                        if (product) {
                            onPick(product);
                            setQuery('');
                        }
                    }}
                    placeholder="Mahsulot nomi yoki shtrix-kod (Enter = +1 dona)"
                    aria-label="Mahsulot qidirish"
                    autoComplete="off"
                    className="h-12 pl-10 text-lg"
                />
            </div>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4">
                {matches.map((product) => {
                    const line = lines.find(
                        (item) => item.product_id === product.id,
                    );
                    const available = stockOf(product.id);

                    return (
                        <button
                            key={product.id}
                            type="button"
                            onClick={() => {
                                onPick(product);
                                setQuery('');
                            }}
                            disabled={available <= 0}
                            tabIndex={-1}
                            className={cn(
                                'relative flex min-h-28 flex-col justify-between gap-1 rounded-xl border bg-background p-3 text-left transition hover:border-primary hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40',
                                line && 'border-primary bg-primary/5',
                            )}
                        >
                            <span className="line-clamp-2 pr-6 font-medium">
                                {product.name}
                            </span>
                            <span className="text-sm tabular-nums">
                                {previousPrices[product.id]
                                    ? formatMoney(previousPrices[product.id])
                                    : "Narx yo'q"}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                Qoldiq:{' '}
                                {formatQuantity(
                                    available,
                                    piecesPerBlock(product.id),
                                )}
                            </span>
                            {line && (
                                <span className="absolute top-2 right-2 rounded-full bg-primary px-2 text-xs text-primary-foreground tabular-nums">
                                    {lineQuantity(line)}
                                </span>
                            )}
                        </button>
                    );
                })}
                {matches.length === 0 && (
                    <p className="col-span-full py-8 text-center text-sm text-muted-foreground">
                        Mahsulot topilmadi
                    </p>
                )}
            </div>
        </section>
    );
}

function CartLine({
    line,
    product,
    quantity,
    sum,
    stock,
    averageCost,
    error,
    onQuantity,
    onLine,
    onRemove,
}: {
    line: DraftLine;
    product: PickerProduct | undefined;
    quantity: number;
    sum: number;
    stock: number;
    averageCost: string | undefined;
    error: (field: string) => string | undefined;
    onQuantity: (pieces: number) => void;
    onLine: (line: DraftLine) => void;
    onRemove: () => void;
}) {
    const perBlock = product?.pieces_per_block ?? 1;
    const belowCost =
        averageCost !== undefined &&
        line.price !== '' &&
        Number(line.price) < Number(averageCost);

    return (
        <li className="grid gap-1 px-3 py-2">
            <div className="flex items-start justify-between gap-2 text-sm">
                <span className="font-medium">{product?.name ?? '—'}</span>
                <span className="flex items-center gap-1">
                    <span className="font-semibold whitespace-nowrap tabular-nums">
                        {formatMoney(sum)}
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-6"
                        onClick={onRemove}
                        aria-label="Qatorni o'chirish"
                    >
                        <X />
                    </Button>
                </span>
            </div>
            <div className="flex flex-wrap items-center gap-1">
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    className="size-7"
                    onClick={() => onQuantity(quantity - 1)}
                    aria-label="1 dona kamaytirish"
                >
                    <Minus />
                </Button>
                {perBlock > 1 && (
                    <>
                        <Input
                            type="number"
                            min={0}
                            step={1}
                            value={line.blocks}
                            onChange={(event) =>
                                onLine({ ...line, blocks: event.target.value })
                            }
                            data-blocks-input={line.key}
                            aria-label="Blok"
                            className="h-7 w-14 text-right tabular-nums"
                        />
                        <span className="text-xs text-muted-foreground">
                            blok
                        </span>
                    </>
                )}
                <Input
                    type="number"
                    min={0}
                    step={1}
                    value={line.pieces}
                    onChange={(event) =>
                        onLine({ ...line, pieces: event.target.value })
                    }
                    data-blocks-input={perBlock > 1 ? undefined : line.key}
                    aria-label="Dona"
                    className="h-7 w-14 text-right tabular-nums"
                />
                <span className="text-xs text-muted-foreground">dona</span>
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    className="size-7"
                    onClick={() => onQuantity(quantity + 1)}
                    aria-label="1 dona qo'shish"
                >
                    <Plus />
                </Button>
                {perBlock > 1 && (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="h-7 px-2"
                        onClick={() => onQuantity(quantity + perBlock)}
                    >
                        +1 blok
                    </Button>
                )}
                <span className="ml-auto text-xs text-muted-foreground">×</span>
                <Input
                    type="number"
                    min={0}
                    step="0.01"
                    value={line.price}
                    onChange={(event) =>
                        onLine({ ...line, price: event.target.value })
                    }
                    aria-label="Narx/dona"
                    className={cn(
                        'h-7 w-28 text-right tabular-nums',
                        line.price === '' && 'border-red-500',
                    )}
                />
            </div>
            <div className="flex flex-wrap justify-between gap-x-2 text-xs">
                <span
                    className={cn(
                        'text-muted-foreground',
                        quantity > stock && 'font-medium text-red-600',
                    )}
                >
                    Qoldiq: {formatQuantity(stock, perBlock)}
                </span>
                {averageCost !== undefined && (
                    <span
                        className={cn(
                            'text-muted-foreground',
                            belowCost && 'font-medium text-amber-600',
                        )}
                    >
                        Tannarx: {formatMoney(averageCost)}
                        {belowCost && " · Narx o'rtacha tannarxdan past"}
                    </span>
                )}
            </div>
            <InputError
                message={error('price') ?? error('pieces') ?? error('blocks')}
            />
        </li>
    );
}

/**
 * Pick a customer by name or phone, or quick-create one; required (and outlined red) only
 * while part of the sale stays unpaid. Shows the chosen customer's current debt.
 */
function CustomerPicker({
    customers,
    customerDebts,
    customer,
    required,
    onChange,
}: {
    customers: SaleCustomer[];
    customerDebts: Record<number, string>;
    customer: SaleCustomer | null;
    required: boolean;
    onChange: (id: number | null) => void;
}) {
    const { can } = usePermissions();
    const [query, setQuery] = useState('');
    const [open, setOpen] = useState(false);
    const term = query.trim().toLowerCase();
    const matches = customers
        .filter(
            (item) =>
                term === '' ||
                item.name.toLowerCase().includes(term) ||
                item.phone?.includes(term),
        )
        .slice(0, 8);

    if (customer) {
        const owes = Number(customerDebts[customer.id] ?? 0);

        return (
            <div className="flex h-9 items-center justify-between gap-2 rounded-md border bg-background px-2 text-sm">
                <span className="min-w-0 truncate">
                    {customer.name}
                    <span
                        className={cn(
                            'ml-1 text-xs text-muted-foreground',
                            owes > 0 && 'text-red-600',
                        )}
                    >
                        qarzi {formatMoney(owes)}
                    </span>
                </span>
                <button
                    type="button"
                    onClick={() => onChange(null)}
                    aria-label="Mijozni olib tashlash"
                    className="text-muted-foreground hover:text-foreground"
                >
                    <X className="size-4" />
                </button>
            </div>
        );
    }

    return (
        <div className="relative">
            <Input
                value={query}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                onBlur={() => setOpen(false)}
                placeholder={
                    required ? 'Mijoz (majburiy)' : 'Mijoz (ixtiyoriy)'
                }
                aria-label="Mijoz"
                className={cn('bg-background', required && 'border-red-500')}
            />
            {required && (
                <p className="mt-1 text-xs text-red-600">
                    Qarzga sotish uchun mijozni tanlang
                </p>
            )}
            {open && (
                <ul className="absolute z-30 mt-1 w-full overflow-hidden rounded-md border bg-popover text-sm shadow-md">
                    {matches.map((item) => (
                        <li key={item.id}>
                            <button
                                type="button"
                                className="flex w-full justify-between gap-2 px-3 py-2 text-left hover:bg-accent"
                                onMouseDown={(event) => {
                                    event.preventDefault();
                                    onChange(item.id);
                                    setQuery('');
                                    setOpen(false);
                                }}
                            >
                                <span className="truncate">{item.name}</span>
                                {customerDebts[item.id] && (
                                    <span className="whitespace-nowrap text-muted-foreground">
                                        {formatMoney(customerDebts[item.id])}
                                    </span>
                                )}
                            </button>
                        </li>
                    ))}
                    {matches.length === 0 && (
                        <li className="px-3 py-2 text-muted-foreground">
                            Topilmadi
                        </li>
                    )}
                </ul>
            )}
            {can('counterparties.create') && (
                <CounterpartyQuickCreate
                    title="Yangi mijoz"
                    onCreated={(created) => onChange(created.id)}
                    trigger={
                        <button
                            type="button"
                            className="mt-1 flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                        >
                            <UserPlus className="size-3" /> Yangi mijoz
                        </button>
                    }
                />
            )}
        </div>
    );
}

/** Received cash → change; a helper only, never stored (story 40). */
function ChangeHelper({
    received,
    onReceived,
    cashDue,
}: {
    received: string;
    onReceived: (value: string) => void;
    /** The Naqd amount, or what is left to pay while Naqd is empty. */
    cashDue: number;
}) {
    return (
        <div className="grid grid-cols-[1fr_8rem] items-center gap-x-2 gap-y-1 rounded-md bg-muted/50 p-2 text-sm">
            <span className="text-muted-foreground">Mijoz berdi</span>
            <Input
                type="number"
                min={0}
                value={received}
                onChange={(event) => onReceived(event.target.value)}
                aria-label="Mijoz bergan naqd"
                className="h-8 bg-background text-right tabular-nums"
            />
            <span className="text-muted-foreground">Qaytim</span>
            <span className="text-right font-medium tabular-nums">
                {formatMoney(Math.max((Number(received) || 0) - cashDue, 0))}
            </span>
        </div>
    );
}

/** Bajarish confirmation; Enter confirms. */
function ExecuteDialog({
    open,
    onOpenChange,
    total,
    debt,
    onConfirm,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    total: number;
    debt: number;
    onConfirm: () => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>Sotuvni bajarish</DialogTitle>
                <p className="text-sm">
                    Jami <strong>{formatMoney(total)}</strong>
                    {debt > 0 && (
                        <>
                            , shundan{' '}
                            <strong className="text-red-600">
                                {formatMoney(debt)}
                            </strong>{' '}
                            qarzga
                        </>
                    )}
                    .
                </p>
                <p className="text-sm text-muted-foreground">
                    {EXECUTE_WARNING}
                </p>
                <DialogFooter className="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={() => onOpenChange(false)}
                    >
                        Yo'q
                    </Button>
                    <Button
                        type="button"
                        autoFocus
                        className="bg-green-600 hover:bg-green-700"
                        onClick={() => {
                            onOpenChange(false);
                            onConfirm();
                        }}
                    >
                        Bajarish
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/** The receipt of the sale just executed, over the fresh screen. */
function FlashedReceipt() {
    const receipt = usePage().flash?.receipt as SaleReceiptData | undefined;
    const [closedId, setClosedId] = useState<number | null>(null);

    if (!receipt || receipt.id === closedId) {
        return null;
    }

    return (
        <Dialog open onOpenChange={() => setClosedId(receipt.id)}>
            <DialogContent className="max-w-sm">
                <DialogTitle className="sr-only">
                    Sotuv №{receipt.number}
                </DialogTitle>
                <SaleReceipt receipt={receipt} />
                <DialogFooter className="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={() => setClosedId(receipt.id)}
                    >
                        Yangi sotuv
                    </Button>
                    <Button type="button" onClick={() => window.print()}>
                        <Printer /> Chop etish
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

SaleCreate.layout = {
    breadcrumbs: [{ title: 'Sotuv', href: create() }],
};
