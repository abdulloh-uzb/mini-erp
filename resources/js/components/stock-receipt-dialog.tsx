import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import AlertError from '@/components/alert-error';
import { selectClassName } from '@/components/cash-form';
import CounterpartyQuickCreate from '@/components/counterparty-quick-create';
import { ProductSearch, QuantityInput } from '@/components/document-form';
import InputError from '@/components/input-error';
import { MoneyInput } from '@/components/money-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { formatBlockPrice, formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import { store } from '@/routes/stock/receipts';
import type { EnumOption, PickerOption, PickerProduct } from '@/types';

export type ReceiptProduct = PickerProduct & { sale_price: string | null };

/** What `StockReportController::receiptOptions` sends. */
export type ReceiptOptions = {
    products: ReceiptProduct[];
    locations: PickerOption[];
    counterparties: PickerOption[];
    methods: EnumOption[];
    /** The previous purchase price per product id. */
    previous_prices: Record<number, string>;
    /** The supplier of the last purchase per product id. */
    previous_suppliers: Record<number, number>;
};

type ReceiptForm = {
    product_id: number | '';
    location_id: number | '';
    counterparty_id: number | '';
    blocks: string;
    pieces: string;
    price: string;
    sale_price: string;
    paid_amount: string;
    payment_method: string;
};

/**
 * + Kirim on Qoldiqlar: new pieces of one product arrive without opening Xaridlar. The cost
 * and sale price are pre-filled with the previous purchase price and the current sale price;
 * the dialog shows the new pieces, the total and, opened from a row, the balance it leads to.
 * Saving executes an ordinary purchase, so it also lands in Xaridlar.
 */
export function StockReceiptDialog({
    options,
    product,
    locationId,
    currentQuantity,
    trigger,
}: {
    options: ReceiptOptions;
    /** Fixes the product (opened from a stock row); otherwise it is searched. */
    product?: ReceiptProduct;
    locationId?: number;
    currentQuantity?: number;
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const defaultLocation = options.locations.some(
        (location) => location.id === locationId,
    )
        ? (locationId as number)
        : (options.locations.at(0)?.id ?? '');

    const defaultsFor = (picked: ReceiptProduct | undefined) => ({
        product_id: picked?.id ?? ('' as const),
        counterparty_id:
            (picked && options.previous_suppliers[picked.id]) || ('' as const),
        price: (picked && options.previous_prices[picked.id]) ?? '',
        sale_price: picked?.sale_price ?? '',
    });

    const initial = (): ReceiptForm => ({
        location_id: defaultLocation,
        blocks: '',
        pieces: '',
        paid_amount: '',
        payment_method: options.methods.at(0)?.id ?? 'cash',
        ...defaultsFor(product),
    });

    const form = useForm<ReceiptForm>(initial());
    const errors = form.errors as Partial<Record<string, string>>;
    const picked =
        product ??
        options.products.find((item) => item.id === form.data.product_id);
    const piecesPerBlock = picked?.pieces_per_block ?? 1;
    const newPieces =
        (Number(form.data.blocks) || 0) * piecesPerBlock +
        (Number(form.data.pieces) || 0);
    const total = newPieces * (Number(form.data.price) || 0);
    const unpaid = total - (Number(form.data.paid_amount) || 0);
    const previousPrice = picked
        ? options.previous_prices[picked.id]
        : undefined;
    const isSaleBelowCost =
        form.data.sale_price !== '' &&
        form.data.price !== '' &&
        Number(form.data.sale_price) < Number(form.data.price);

    const reset = () => {
        form.setData(initial());
        form.clearErrors();
    };

    const submit = () => {
        form.transform((data) => ({
            ...data,
            blocks: data.blocks === '' ? 0 : Number(data.blocks),
            pieces: data.pieces === '' ? 0 : Number(data.pieces),
            sale_price: data.sale_price === '' ? null : data.sale_price,
            paid_amount: data.paid_amount === '' ? null : data.paid_amount,
        }));
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);

                if (!value) {
                    reset();
                }
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogTitle>
                    {product ? `${product.name}: kirim` : 'Tovar kirimi'}
                </DialogTitle>
                <DialogDescription>
                    Yangi kelgan tovar xarid sifatida bajariladi va Xaridlarda
                    ko'rinadi.
                </DialogDescription>

                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                >
                    {errors.receipt && <AlertError errors={[errors.receipt]} />}

                    {!product && (
                        <div className="grid gap-2">
                            <Label>Mahsulot</Label>
                            <ProductSearch
                                products={options.products}
                                onPick={(item) =>
                                    form.setData({
                                        ...form.data,
                                        ...defaultsFor(item as ReceiptProduct),
                                    })
                                }
                            />
                            {picked && (
                                <p className="text-sm font-medium">
                                    {picked.name}
                                </p>
                            )}
                            <InputError message={errors.product_id} />
                        </div>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="receipt-location">Joy</Label>
                            <select
                                id="receipt-location"
                                value={form.data.location_id}
                                onChange={(event) =>
                                    form.setData(
                                        'location_id',
                                        Number(event.target.value) || '',
                                    )
                                }
                                className={selectClassName}
                            >
                                {options.locations.map((location) => (
                                    <option
                                        key={location.id}
                                        value={location.id}
                                    >
                                        {location.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.location_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="receipt-supplier">
                                Yetkazib beruvchi
                            </Label>
                            <div className="flex gap-2">
                                <select
                                    id="receipt-supplier"
                                    value={form.data.counterparty_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'counterparty_id',
                                            Number(event.target.value) || '',
                                        )
                                    }
                                    className={selectClassName}
                                >
                                    <option value="">Tanlang</option>
                                    {options.counterparties.map(
                                        (counterparty) => (
                                            <option
                                                key={counterparty.id}
                                                value={counterparty.id}
                                            >
                                                {counterparty.name}
                                            </option>
                                        ),
                                    )}
                                </select>
                                <CounterpartyQuickCreate
                                    title="Yangi yetkazib beruvchi"
                                    trigger={
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            aria-label="Yangi yetkazib beruvchi"
                                        >
                                            <Plus />
                                        </Button>
                                    }
                                    onCreated={(counterparty) =>
                                        form.setData(
                                            'counterparty_id',
                                            counterparty.id,
                                        )
                                    }
                                />
                            </div>
                            <InputError message={errors.counterparty_id} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label>Yangi kelgan miqdor</Label>
                        <div className="flex flex-wrap items-center gap-2 text-sm">
                            {piecesPerBlock > 1 && (
                                <>
                                    <QuantityInput
                                        label="Blok"
                                        value={form.data.blocks}
                                        onChange={(value) =>
                                            form.setData('blocks', value)
                                        }
                                    />
                                    <span>blok +</span>
                                </>
                            )}
                            <QuantityInput
                                label="Dona"
                                value={form.data.pieces}
                                onChange={(value) =>
                                    form.setData('pieces', value)
                                }
                            />
                            <span>dona</span>
                            {piecesPerBlock > 1 && (
                                <span className="text-muted-foreground">
                                    (1 blok = {piecesPerBlock} dona)
                                </span>
                            )}
                        </div>
                        <InputError message={errors.pieces ?? errors.blocks} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="receipt-price">
                                Tannarx (1 dona)
                            </Label>
                            <MoneyInput
                                id="receipt-price"
                                placeholder="0"
                                value={form.data.price}
                                onValueChange={(amount) =>
                                    form.setData('price', amount)
                                }
                            />
                            <PriceHint
                                lines={[
                                    previousPrice !== undefined
                                        ? `Oldingi: ${formatMoney(previousPrice)}`
                                        : null,
                                    formatBlockPrice(
                                        form.data.price,
                                        piecesPerBlock,
                                    ),
                                ]}
                            />
                            <InputError message={errors.price} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="receipt-sale-price">
                                Sotuv narxi (1 dona)
                            </Label>
                            <MoneyInput
                                id="receipt-sale-price"
                                placeholder="0"
                                value={form.data.sale_price}
                                onValueChange={(amount) =>
                                    form.setData('sale_price', amount)
                                }
                                className={cn(
                                    isSaleBelowCost &&
                                        'text-red-600 dark:text-red-400',
                                )}
                            />
                            <PriceHint
                                lines={[
                                    picked?.sale_price
                                        ? `Hozirgi: ${formatMoney(picked.sale_price)}`
                                        : null,
                                    formatBlockPrice(
                                        form.data.sale_price,
                                        piecesPerBlock,
                                    ),
                                    isSaleBelowCost
                                        ? 'Sotuv narxi tannarxdan past'
                                        : null,
                                ]}
                            />
                            <InputError message={errors.sale_price} />
                        </div>
                    </div>

                    <dl className="grid gap-1 rounded-lg border bg-muted/40 p-3 text-sm">
                        <SummaryRow
                            label="Yangi dona"
                            value={`${newPieces} dona`}
                        />
                        {currentQuantity !== undefined && (
                            <SummaryRow
                                label="Qoldiq"
                                value={`${currentQuantity} → ${currentQuantity + newPieces} dona`}
                            />
                        )}
                        <SummaryRow
                            label="Jami narxi"
                            value={formatMoney(total)}
                            strong
                        />
                    </dl>

                    <div className="grid gap-2">
                        <Label htmlFor="receipt-paid">
                            To'langan summa (ixtiyoriy)
                        </Label>
                        <div className="flex gap-2">
                            <MoneyInput
                                id="receipt-paid"
                                placeholder="0"
                                value={form.data.paid_amount}
                                onValueChange={(amount) =>
                                    form.setData('paid_amount', amount)
                                }
                            />
                            <select
                                aria-label="To'lov usuli"
                                value={form.data.payment_method}
                                onChange={(event) =>
                                    form.setData(
                                        'payment_method',
                                        event.target.value,
                                    )
                                }
                                className={cn(selectClassName, 'w-44')}
                            >
                                {options.methods.map((method) => (
                                    <option key={method.id} value={method.id}>
                                        {method.name}
                                    </option>
                                ))}
                            </select>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    form.setData(
                                        'paid_amount',
                                        total > 0
                                            ? String(
                                                  Math.round(total * 100) / 100,
                                              )
                                            : '',
                                    )
                                }
                            >
                                To'liq
                            </Button>
                        </div>
                        {unpaid > 0 && form.data.counterparty_id !== '' && (
                            <p className="text-sm text-muted-foreground">
                                Yetkazib beruvchiga qarz qoladi:{' '}
                                {formatMoney(unpaid)}
                            </p>
                        )}
                        <InputError
                            message={
                                errors.paid_amount ?? errors.payment_method
                            }
                        />
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setOpen(false)}
                        >
                            Bekor qilish
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing || picked === undefined}
                        >
                            Kirim qilish
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function PriceHint({ lines }: { lines: (string | null)[] }) {
    const shown = lines.filter((line): line is string => line !== null);

    if (shown.length === 0) {
        return null;
    }

    return (
        <div className="text-xs text-muted-foreground">
            {shown.map((line) => (
                <div key={line}>{line}</div>
            ))}
        </div>
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
            <dd
                className={cn(
                    'tabular-nums',
                    strong && 'text-base font-semibold',
                )}
            >
                {value}
            </dd>
        </div>
    );
}
