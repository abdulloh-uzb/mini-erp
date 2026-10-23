import { Link } from '@inertiajs/react';
import { Search, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
import { formatDateTime, formatMoney, formatQuantity } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    DocumentData,
    DocumentStatus,
    PickerOption,
    PickerProduct,
} from '@/types';

/**
 * The shared document-form pattern (spec: Frontend): header, lines with Blok + Dona inputs,
 * product search/scan adding +1 dona, a totals panel, and the buttons Saqlash, Bajarish
 * (always confirmed), O'chirish and Bekor qilish. Each document page composes these.
 */

export const EXECUTE_WARNING =
    "Bajarilgan hujjatni o'zgartirib yoki o'chirib bo'lmaydi. Davom etasizmi?";

/** A line being edited. `key` is local; quantities are entered as blocks + pieces. */
export type DraftLine = {
    key: string;
    product_id: number;
    location_id: number | null;
    blocks: string;
    pieces: string;
    price: string;
};

export type LineErrors = Record<string, string | undefined>;

let nextLineKey = 0;

export function newLineKey(): string {
    nextLineKey += 1;

    return `line-${nextLineKey}`;
}

export function linePieces(line: DraftLine, piecesPerBlock: number): number {
    return (
        (Number(line.blocks) || 0) * piecesPerBlock + (Number(line.pieces) || 0)
    );
}

export function DocumentStatusBadge({ status }: { status: DocumentStatus }) {
    return status === 'executed' ? (
        <Badge className="bg-green-600 text-white dark:bg-green-700">
            Bajarilgan
        </Badge>
    ) : (
        <Badge variant="secondary">Qoralama</Badge>
    );
}

/** "Yaratdi: Ali · Bajardi: Egasi" for audit. */
export function DocumentAudit({ document }: { document: DocumentData }) {
    return (
        <p className="text-sm text-muted-foreground">
            Yaratdi: {document.author}
            {document.executor && (
                <>
                    {' · '}Bajardi: {document.executor},{' '}
                    {formatDateTime(document.executed_at)}
                </>
            )}
        </p>
    );
}

/**
 * Finds a product by name or barcode. Enter (or a scanner, which types and presses Enter)
 * picks an exact barcode match, else the first match; the box keeps focus for the next scan.
 */
export function ProductSearch({
    products,
    onPick,
}: {
    products: PickerProduct[];
    onPick: (product: PickerProduct) => void;
}) {
    const [query, setQuery] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);
    const term = query.trim().toLowerCase();

    const matches = useMemo(() => {
        if (term === '') {
            return [];
        }

        return products
            .filter(
                (product) =>
                    product.name.toLowerCase().includes(term) ||
                    product.barcode?.includes(term),
            )
            .slice(0, 8);
    }, [products, term]);

    const pick = (product: PickerProduct) => {
        onPick(product);
        setQuery('');
        inputRef.current?.focus();
    };

    return (
        <div className="relative w-full max-w-md">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                ref={inputRef}
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                onKeyDown={(event) => {
                    if (event.key !== 'Enter') {
                        return;
                    }

                    event.preventDefault();
                    const product =
                        products.find(
                            (item) => item.barcode === query.trim(),
                        ) ?? matches[0];

                    if (product) {
                        pick(product);
                    }
                }}
                placeholder="Mahsulot nomi yoki shtrix-kod"
                aria-label="Mahsulot qidirish"
                className="pl-9"
                autoComplete="off"
            />
            {matches.length > 0 && (
                <ul className="absolute z-20 mt-1 w-full overflow-hidden rounded-md border bg-popover shadow-md">
                    {matches.map((product) => (
                        <li key={product.id}>
                            <button
                                type="button"
                                className="flex w-full justify-between px-3 py-2 text-left text-sm hover:bg-accent"
                                onClick={() => pick(product)}
                            >
                                <span>{product.name}</span>
                                <span className="text-muted-foreground">
                                    1 blok = {product.pieces_per_block} dona
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/**
 * The editable lines table. Columns: optional Joy, Mahsulot, Blok, Dona, Jami dona, optional
 * price and Summa. Errors are keyed like the server's (`lines.N.field`).
 */
export function DocumentLinesTable({
    lines,
    products,
    locations,
    priceLabel = 'Narx/dona',
    showPrice = true,
    errors,
    onChange,
    onRemove,
}: {
    lines: DraftLine[];
    products: Map<number, PickerProduct>;
    locations?: PickerOption[];
    priceLabel?: string;
    showPrice?: boolean;
    errors: LineErrors;
    onChange: (index: number, line: DraftLine) => void;
    onRemove: (index: number) => void;
}) {
    const columns = [
        locations ? 'Joy' : null,
        'Mahsulot',
        'Blok',
        'Dona',
        'Jami dona',
        showPrice ? priceLabel : null,
        showPrice ? 'Summa' : null,
        '',
    ].filter((column) => column !== null);

    return (
        <div className="overflow-x-auto rounded-xl border">
            <table className="w-full text-sm">
                <thead className="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        {columns.map((column, index) => (
                            <th key={index} className="px-3 py-2 font-medium">
                                {column}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {lines.map((line, index) => {
                        const product = products.get(line.product_id);
                        const piecesPerBlock = product?.pieces_per_block ?? 1;
                        const total = linePieces(line, piecesPerBlock);
                        const error = (field: string) =>
                            errors[`lines.${index}.${field}`];
                        const update = (changes: Partial<DraftLine>) =>
                            onChange(index, { ...line, ...changes });

                        return (
                            <tr key={line.key} className="border-t align-top">
                                {locations && (
                                    <td className="px-3 py-2">
                                        <select
                                            value={line.location_id ?? ''}
                                            onChange={(event) =>
                                                update({
                                                    location_id:
                                                        Number(
                                                            event.target.value,
                                                        ) || null,
                                                })
                                            }
                                            aria-label="Joy"
                                            className="h-9 w-44 rounded-md border border-input bg-transparent px-2 text-sm"
                                        >
                                            <option value="">Tanlang</option>
                                            {locations.map((location) => (
                                                <option
                                                    key={location.id}
                                                    value={location.id}
                                                >
                                                    {location.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={error('location_id')}
                                        />
                                    </td>
                                )}
                                <td className="px-3 py-2">
                                    <div className="font-medium">
                                        {product?.name ?? '—'}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        1 blok = {piecesPerBlock} dona
                                    </div>
                                    <InputError message={error('product_id')} />
                                </td>
                                <td className="px-3 py-2">
                                    <QuantityInput
                                        label="Blok"
                                        value={line.blocks}
                                        onChange={(blocks) =>
                                            update({ blocks })
                                        }
                                    />
                                    <InputError message={error('blocks')} />
                                </td>
                                <td className="px-3 py-2">
                                    <QuantityInput
                                        label="Dona"
                                        value={line.pieces}
                                        onChange={(pieces) =>
                                            update({ pieces })
                                        }
                                    />
                                    <InputError message={error('pieces')} />
                                </td>
                                <td className="px-3 py-2 tabular-nums">
                                    {total}
                                </td>
                                {showPrice && (
                                    <>
                                        <td className="px-3 py-2">
                                            <Input
                                                type="number"
                                                min={0}
                                                step="0.01"
                                                value={line.price}
                                                onChange={(event) =>
                                                    update({
                                                        price: event.target
                                                            .value,
                                                    })
                                                }
                                                aria-label={priceLabel}
                                                className="w-32"
                                            />
                                            <InputError
                                                message={error('price')}
                                            />
                                        </td>
                                        <td className="px-3 py-2 whitespace-nowrap tabular-nums">
                                            {formatMoney(
                                                total *
                                                    (Number(line.price) || 0),
                                            )}
                                        </td>
                                    </>
                                )}
                                <td className="px-3 py-2 text-right">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => onRemove(index)}
                                        aria-label="Qatorni o'chirish"
                                    >
                                        <Trash2 />
                                    </Button>
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

function QuantityInput({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <Input
            type="number"
            min={0}
            step={1}
            value={value}
            onChange={(event) => onChange(event.target.value)}
            aria-label={label}
            className="w-20"
        />
    );
}

/** Totals panel: number of lines, pieces and, where prices are shown, the sum. */
export function DocumentTotals({
    lines,
    products,
    showPrice = true,
    sumLabel = 'Jami summa',
}: {
    lines: DraftLine[];
    products: Map<number, PickerProduct>;
    showPrice?: boolean;
    sumLabel?: string;
}) {
    const pieces = lines.reduce(
        (sum, line) =>
            sum +
            linePieces(
                line,
                products.get(line.product_id)?.pieces_per_block ?? 1,
            ),
        0,
    );
    const amount = lines.reduce(
        (sum, line) =>
            sum +
            linePieces(
                line,
                products.get(line.product_id)?.pieces_per_block ?? 1,
            ) *
                (Number(line.price) || 0),
        0,
    );

    return (
        <dl className="grid w-full max-w-xs gap-2 rounded-xl border p-4 text-sm">
            <TotalRow label="Qatorlar" value={String(lines.length)} />
            <TotalRow label="Jami dona" value={String(pieces)} />
            {showPrice && (
                <TotalRow label={sumLabel} value={formatMoney(amount)} strong />
            )}
        </dl>
    );
}

function TotalRow({
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

/**
 * Saqlash, Bajarish (only when permitted; always confirmed), O'chirish (saved drafts) and
 * Bekor qilish (leave without saving).
 */
export function DocumentFormActions({
    processing,
    cancelHref,
    onSave,
    onExecute,
    onDelete,
    executeDescription = EXECUTE_WARNING,
}: {
    processing: boolean;
    cancelHref: string;
    onSave: () => void;
    onExecute?: () => void;
    onDelete?: () => void;
    executeDescription?: string;
}) {
    return (
        <div className="flex flex-wrap items-center gap-2">
            <Button type="button" onClick={onSave} disabled={processing}>
                Saqlash
            </Button>
            {onExecute && (
                <ConfirmDialog
                    trigger={
                        <Button
                            type="button"
                            variant="default"
                            className="bg-green-600 hover:bg-green-700"
                            disabled={processing}
                        >
                            Bajarish
                        </Button>
                    }
                    title="Hujjatni bajarish"
                    description={executeDescription}
                    confirmLabel="Bajarish"
                    onConfirm={onExecute}
                />
            )}
            {onDelete && (
                <ConfirmDialog
                    trigger={
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={processing}
                        >
                            O'chirish
                        </Button>
                    }
                    title="Qoralamani o'chirish"
                    description="Qoralama butunlay o'chiriladi. Davom etasizmi?"
                    confirmLabel="O'chirish"
                    destructive
                    onConfirm={onDelete}
                />
            )}
            <Button variant="outline" asChild>
                <Link href={cancelHref}>Bekor qilish</Link>
            </Button>
        </div>
    );
}

export function ConfirmDialog({
    trigger,
    title,
    description,
    confirmLabel,
    destructive = false,
    onConfirm,
}: {
    trigger: ReactNode;
    title: string;
    description: string;
    confirmLabel: string;
    destructive?: boolean;
    onConfirm: () => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            Yo'q
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant={destructive ? 'destructive' : 'default'}
                        onClick={() => {
                            setOpen(false);
                            onConfirm();
                        }}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/** An executed document's lines, read-only. */
export function ExecutedLinesTable({
    document,
    priceLabel = 'Narx/dona',
    showPrice = true,
    showLocation = false,
}: {
    document: DocumentData;
    priceLabel?: string;
    showPrice?: boolean;
    showLocation?: boolean;
}) {
    return (
        <div className="overflow-x-auto rounded-xl border">
            <table className="w-full text-sm">
                <thead className="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        {showLocation && (
                            <th className="px-4 py-3 font-medium">Joy</th>
                        )}
                        <th className="px-4 py-3 font-medium">Mahsulot</th>
                        <th className="px-4 py-3 font-medium">Miqdor</th>
                        {showPrice && (
                            <>
                                <th className="px-4 py-3 font-medium">
                                    {priceLabel}
                                </th>
                                <th className="px-4 py-3 font-medium">Summa</th>
                            </>
                        )}
                    </tr>
                </thead>
                <tbody>
                    {document.lines.map((line) => (
                        <tr key={line.id} className="border-t">
                            {showLocation && (
                                <td className="px-4 py-3">
                                    {line.location_name}
                                </td>
                            )}
                            <td className="px-4 py-3 font-medium">
                                {line.product_name}
                            </td>
                            <td className="px-4 py-3 tabular-nums">
                                {formatQuantity(
                                    line.quantity,
                                    line.pieces_per_block,
                                )}
                            </td>
                            {showPrice && (
                                <>
                                    <td className="px-4 py-3 tabular-nums">
                                        {formatMoney(line.price)}
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">
                                        {formatMoney(
                                            line.quantity *
                                                Number(line.price ?? 0),
                                        )}
                                    </td>
                                </>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
