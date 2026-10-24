import { Head, router, useForm } from '@inertiajs/react';
import { ListPlus, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import InventoryCountController from '@/actions/App/Http/Controllers/InventoryCountController';
import AlertError from '@/components/alert-error';
import { selectClassName } from '@/components/cash-form';
import {
    DocumentAudit,
    DocumentFormActions,
    DocumentStatusBadge,
    EXECUTE_WARNING,
    linePieces,
    newLineKey,
    ProductSearch,
    QuantityInput,
} from '@/components/document-form';
import type { DraftLine, LineErrors } from '@/components/document-form';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney, formatQuantity, splitQuantity } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index } from '@/routes/inventory-counts';
import type { DocumentData, PickerOption, PickerProduct } from '@/types';

type Props = {
    document: DocumentData | null;
    editable: boolean;
    locations: PickerOption[];
    products: PickerProduct[];
    /** Product ids with stock, per location id ("Joydagi barcha mahsulotlarni qo'shish"). */
    stocked_products: Record<number, number[]>;
    /** Pieces on hand per location id, then product id; only for users who may execute counts. */
    expected?: Record<number, Record<number, number>>;
    /** Average cost per product id; only for users who may execute counts and view costs. */
    average_costs?: Record<number, string>;
};

type FieldErrors = Record<string, string | undefined>;

/** One reviewed line: counted vs expected, the difference and its value at average cost. */
type Review = {
    counted: number;
    expected: number;
    difference: number;
    value: number | null;
};

export default function InventoryCountShow({
    document,
    editable,
    locations,
    products,
    stocked_products: stockedProducts,
    expected,
    average_costs: averageCosts,
}: Props) {
    const title = document
        ? `Inventarizatsiya №${document.number}`
        : 'Yangi inventarizatsiya';

    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={title}
                        description="Joydagi tovarlarni sanash. Faqat ro'yxatdagi mahsulotlar qoldig'i o'zgaradi."
                    />
                    {document && (
                        <DocumentStatusBadge status={document.status} />
                    )}
                </div>

                {editable ? (
                    <CountForm
                        document={document}
                        locations={locations}
                        products={products}
                        stockedProducts={stockedProducts}
                        expected={expected}
                        averageCosts={averageCosts}
                    />
                ) : (
                    document && <ReadOnlyCount document={document} />
                )}
            </div>
        </>
    );
}

function ReadOnlyCount({ document }: { document: DocumentData }) {
    const reviewed = document.lines.some(
        (line) => line.expected_quantity != null,
    );
    const valued = document.lines.some((line) => line.difference_value != null);
    const reviews = document.lines.map((line): Review => ({
        counted: line.quantity,
        expected: line.expected_quantity ?? 0,
        difference: line.quantity - (line.expected_quantity ?? 0),
        value:
            line.difference_value == null
                ? null
                : Number(line.difference_value),
    }));

    return (
        <div className="flex flex-col gap-4">
            <DocumentAudit document={document} />
            <dl className="grid max-w-xl grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                <dt className="text-muted-foreground">Joy</dt>
                <dd>{document.location_name ?? '—'}</dd>
                {document.note && (
                    <>
                        <dt className="text-muted-foreground">Izoh</dt>
                        <dd>{document.note}</dd>
                    </>
                )}
            </dl>
            <div className="overflow-x-auto rounded-xl border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th className="px-4 py-3 font-medium">Mahsulot</th>
                            <th className="px-4 py-3 font-medium">Sanalgan</th>
                            {reviewed && <ReviewHeaders valued={valued} />}
                        </tr>
                    </thead>
                    <tbody>
                        {document.lines.map((line, position) => (
                            <tr key={line.id} className="border-t">
                                <td className="px-4 py-3 font-medium">
                                    {line.product_name}
                                </td>
                                <td className="px-4 py-3 tabular-nums">
                                    {formatQuantity(
                                        line.quantity,
                                        line.pieces_per_block,
                                    )}
                                </td>
                                {reviewed && (
                                    <ReviewCells
                                        review={reviews[position]}
                                        piecesPerBlock={line.pieces_per_block}
                                        valued={valued}
                                    />
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {valued && <DifferenceTotals reviews={reviews} />}
            {document.status === 'draft' && (
                <p className="text-sm text-muted-foreground">
                    Egasi bajarishini kutmoqda.
                </p>
            )}
        </div>
    );
}

function CountForm({
    document,
    locations,
    products,
    stockedProducts,
    expected,
    averageCosts,
}: {
    document: DocumentData | null;
    locations: PickerOption[];
    products: PickerProduct[];
    stockedProducts: Record<number, number[]>;
    expected?: Record<number, Record<number, number>>;
    averageCosts?: Record<number, string>;
}) {
    const canExecute = expected !== undefined;
    const valued = averageCosts !== undefined;
    const productsById = useMemo(
        () => new Map(products.map((product) => [product.id, product])),
        [products],
    );

    const form = useForm({
        location_id: (document?.location_id ?? locations.at(0)?.id ?? '') as
            | number
            | '',
        note: document?.note ?? '',
        lines: (document?.lines ?? []).map((line): DraftLine => {
            const split = splitQuantity(line.quantity, line.pieces_per_block);

            return {
                key: newLineKey(),
                product_id: line.product_id,
                location_id: null,
                blocks: String(split.blocks),
                pieces: String(split.pieces),
                price: '',
            };
        }),
    });
    const errors = form.errors as LineErrors & FieldErrors;
    const piecesPerBlock = (productId: number) =>
        productsById.get(productId)?.pieces_per_block ?? 1;

    const newLine = (productId: number, pieces: string): DraftLine => ({
        key: newLineKey(),
        product_id: productId,
        location_id: null,
        blocks: '',
        pieces,
        price: '',
    });

    const addProduct = (product: PickerProduct) => {
        const index = form.data.lines.findIndex(
            (line) => line.product_id === product.id,
        );

        if (index < 0) {
            form.setData('lines', [
                ...form.data.lines,
                newLine(product.id, '1'),
            ]);

            return;
        }

        const line = form.data.lines[index];
        replaceLine(index, {
            ...line,
            pieces: String((Number(line.pieces) || 0) + 1),
        });
    };

    const addAllAtLocation = () => {
        const listed = new Set(form.data.lines.map((line) => line.product_id));
        const missing = (
            form.data.location_id === ''
                ? []
                : (stockedProducts[form.data.location_id] ?? [])
        ).filter(
            (productId) =>
                !listed.has(productId) && productsById.has(productId),
        );

        form.setData('lines', [
            ...form.data.lines,
            ...missing.map((productId) => newLine(productId, '')),
        ]);
    };

    const replaceLine = (index: number, line: DraftLine) =>
        form.setData(
            'lines',
            form.data.lines.map((current, position) =>
                position === index ? line : current,
            ),
        );

    const review = (line: DraftLine): Review => {
        const counted = linePieces(line, piecesPerBlock(line.product_id));
        const onHand =
            form.data.location_id === ''
                ? 0
                : (expected?.[form.data.location_id]?.[line.product_id] ?? 0);
        const difference = counted - onHand;

        return {
            counted,
            expected: onHand,
            difference,
            value: valued
                ? difference * Number(averageCosts[line.product_id] ?? 0)
                : null,
        };
    };
    const reviews = form.data.lines.map(review);

    const submit = (execute: boolean) => {
        form.transform((data) => ({
            location_id: data.location_id,
            note: data.note === '' ? null : data.note,
            lines: data.lines.map((line) => ({
                product_id: line.product_id,
                blocks: line.blocks === '' ? 0 : Number(line.blocks),
                pieces: line.pieces === '' ? 0 : Number(line.pieces),
            })),
            execute,
        }));

        const options = {
            preserveScroll: true,
            preserveState: 'errors' as const,
        };

        if (document) {
            form.put(InventoryCountController.update.url(document.id), options);
        } else {
            form.post(InventoryCountController.store.url(), options);
        }
    };

    const { shortage, surplus } = differenceTotals(reviews);

    return (
        <div className="flex flex-col gap-4">
            {document && <DocumentAudit document={document} />}
            {errors.document && <AlertError errors={[errors.document]} />}

            <div className="grid gap-2">
                <Label htmlFor="count-location">Joy</Label>
                <select
                    id="count-location"
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

            <div className="flex flex-wrap items-center gap-3">
                <ProductSearch products={products} onPick={addProduct} />
                <Button
                    type="button"
                    variant="outline"
                    onClick={addAllAtLocation}
                >
                    <ListPlus />
                    Joydagi barcha mahsulotlarni qo'shish
                </Button>
            </div>
            <InputError message={errors.lines} />

            {form.data.lines.length === 0 ? (
                <p className="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                    Mahsulotni skanerlang, qidiring yoki joydagi barcha
                    mahsulotlarni qo'shing.
                </p>
            ) : (
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Mahsulot
                                </th>
                                <th className="px-3 py-2 font-medium">Blok</th>
                                <th className="px-3 py-2 font-medium">Dona</th>
                                <th className="px-3 py-2 font-medium">
                                    Sanalgan
                                </th>
                                {canExecute && (
                                    <ReviewHeaders valued={valued} />
                                )}
                                <th className="px-3 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {form.data.lines.map((line, position) => {
                                const product = productsById.get(
                                    line.product_id,
                                );
                                const error = (field: string) =>
                                    errors[`lines.${position}.${field}`];

                                return (
                                    <tr
                                        key={line.key}
                                        className="border-t align-top"
                                    >
                                        <td className="px-3 py-2">
                                            <div className="font-medium">
                                                {product?.name ?? '—'}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                1 blok ={' '}
                                                {piecesPerBlock(
                                                    line.product_id,
                                                )}{' '}
                                                dona
                                            </div>
                                            <InputError
                                                message={error('product_id')}
                                            />
                                        </td>
                                        <td className="px-3 py-2">
                                            <QuantityInput
                                                label="Blok"
                                                value={line.blocks}
                                                onChange={(blocks) =>
                                                    replaceLine(position, {
                                                        ...line,
                                                        blocks,
                                                    })
                                                }
                                            />
                                            <InputError
                                                message={error('blocks')}
                                            />
                                        </td>
                                        <td className="px-3 py-2">
                                            <QuantityInput
                                                label="Dona"
                                                value={line.pieces}
                                                onChange={(pieces) =>
                                                    replaceLine(position, {
                                                        ...line,
                                                        pieces,
                                                    })
                                                }
                                            />
                                            <InputError
                                                message={error('pieces')}
                                            />
                                        </td>
                                        <td className="px-3 py-2 whitespace-nowrap tabular-nums">
                                            {formatQuantity(
                                                reviews[position].counted,
                                                piecesPerBlock(line.product_id),
                                            )}
                                        </td>
                                        {canExecute && (
                                            <ReviewCells
                                                review={reviews[position]}
                                                piecesPerBlock={piecesPerBlock(
                                                    line.product_id,
                                                )}
                                                valued={valued}
                                                compact
                                            />
                                        )}
                                        <td className="px-3 py-2 text-right">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    form.setData(
                                                        'lines',
                                                        form.data.lines.filter(
                                                            (_, index) =>
                                                                index !==
                                                                position,
                                                        ),
                                                    )
                                                }
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
            )}

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="grid w-full max-w-md gap-2">
                    <Label htmlFor="count-note">Izoh (ixtiyoriy)</Label>
                    <Input
                        id="count-note"
                        value={form.data.note}
                        onChange={(event) =>
                            form.setData('note', event.target.value)
                        }
                    />
                    <InputError message={errors.note} />
                </div>
                {valued ? (
                    <DifferenceTotals reviews={reviews} />
                ) : (
                    <dl className="grid w-full max-w-xs gap-2 rounded-xl border p-4 text-sm">
                        <TotalRow
                            label="Qatorlar"
                            value={String(form.data.lines.length)}
                        />
                    </dl>
                )}
            </div>

            {canExecute ? (
                <p className="text-sm text-muted-foreground">
                    Kutilgan qoldiq bajarish paytida qayta hisoblanadi.
                </p>
            ) : (
                document && (
                    <p className="text-sm text-muted-foreground">
                        Egasi bajarishini kutmoqda.
                    </p>
                )
            )}

            <DocumentFormActions
                processing={form.processing}
                cancelHref={index.url()}
                onSave={() => submit(false)}
                onExecute={canExecute ? () => submit(true) : undefined}
                executeDescription={
                    valued
                        ? `Kamomad: ${formatMoney(shortage)}. Ortiqcha: ${formatMoney(surplus)}. ${EXECUTE_WARNING}`
                        : EXECUTE_WARNING
                }
                onDelete={
                    document
                        ? () =>
                              router.delete(
                                  InventoryCountController.destroy.url(
                                      document.id,
                                  ),
                              )
                        : undefined
                }
            />
        </div>
    );
}

function ReviewHeaders({ valued }: { valued: boolean }) {
    return (
        <>
            <th className="px-3 py-2 font-medium">Kutilgan</th>
            <th className="px-3 py-2 font-medium">Farq</th>
            {valued && <th className="px-3 py-2 font-medium">Farq summasi</th>}
        </>
    );
}

/** Kutilgan, Farq (red for a shortage, green for a surplus) and, with costs, its value. */
function ReviewCells({
    review,
    piecesPerBlock,
    valued,
    compact = false,
}: {
    review: Review;
    piecesPerBlock: number;
    valued: boolean;
    compact?: boolean;
}) {
    const cell = cn(
        'whitespace-nowrap tabular-nums',
        compact ? 'px-3 py-2' : 'px-4 py-3',
    );
    const tone = cn(
        review.difference < 0 && 'text-red-600 dark:text-red-400',
        review.difference > 0 && 'text-green-600 dark:text-green-400',
    );

    return (
        <>
            <td className={cell}>
                {formatQuantity(review.expected, piecesPerBlock)}
            </td>
            <td className={cn(cell, tone)}>
                {review.difference > 0 ? '+' : review.difference < 0 ? '−' : ''}
                {formatQuantity(Math.abs(review.difference), piecesPerBlock)}
            </td>
            {valued && (
                <td className={cn(cell, tone)}>
                    {formatMoney(review.value ?? 0)}
                </td>
            )}
        </>
    );
}

function differenceTotals(reviews: Review[]): {
    shortage: number;
    surplus: number;
} {
    return reviews.reduce(
        (totals, review) => ({
            shortage: totals.shortage + Math.max(-(review.value ?? 0), 0),
            surplus: totals.surplus + Math.max(review.value ?? 0, 0),
        }),
        { shortage: 0, surplus: 0 },
    );
}

/** Kamomad (shortage) and Ortiqcha (surplus) totals at average cost. */
function DifferenceTotals({ reviews }: { reviews: Review[] }) {
    const { shortage, surplus } = differenceTotals(reviews);

    return (
        <dl className="grid w-full max-w-xs gap-2 rounded-xl border p-4 text-sm">
            <TotalRow label="Qatorlar" value={String(reviews.length)} />
            <TotalRow label="Kamomad" value={formatMoney(shortage)} strong />
            <TotalRow label="Ortiqcha" value={formatMoney(surplus)} strong />
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

InventoryCountShow.layout = {
    breadcrumbs: [
        {
            title: 'Inventarizatsiya',
            href: index(),
        },
    ],
};
