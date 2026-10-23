import { Head, Link, router, useForm } from '@inertiajs/react';
import { Undo2 } from 'lucide-react';
import { useMemo } from 'react';
import TransferController from '@/actions/App/Http/Controllers/TransferController';
import AlertError from '@/components/alert-error';
import { selectClassName } from '@/components/cash-form';
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
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import { formatQuantity, splitQuantity } from '@/lib/format';
import { cn } from '@/lib/utils';
import { create, index } from '@/routes/transfers';
import type { DocumentData, PickerOption, PickerProduct } from '@/types';

/** A reverse transfer's starting point: the reversed transfer with its locations swapped. */
type Prefill = {
    location_id: number;
    destination_location_id: number;
    lines: { product_id: number; quantity: number; pieces_per_block: number }[];
};

type Props = {
    document: DocumentData | null;
    editable: boolean;
    prefill: Prefill | null;
    locations: PickerOption[];
    products: PickerProduct[];
    /** Pieces on hand per location id, then per product id. */
    stock: Record<number, Record<number, number>>;
};

type FieldErrors = Record<string, string | undefined>;

export default function TransferShow({
    document,
    editable,
    prefill,
    locations,
    products,
    stock,
}: Props) {
    const title = document
        ? `Ko'chirish №${document.number}`
        : "Yangi ko'chirish";

    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title={title}
                        description="Tovarlarni bir joydan boshqasiga ko'chirish. Pul va tannarxga ta'sir qilmaydi."
                    />
                    {document && (
                        <DocumentStatusBadge status={document.status} />
                    )}
                </div>

                {editable ? (
                    <TransferForm
                        document={document}
                        prefill={prefill}
                        locations={locations}
                        products={products}
                        stock={stock}
                    />
                ) : (
                    document && <ReadOnlyTransfer document={document} />
                )}
            </div>
        </>
    );
}

function ReadOnlyTransfer({ document }: { document: DocumentData }) {
    return (
        <div className="flex flex-col gap-4">
            <DocumentAudit document={document} />
            <dl className="grid max-w-xl grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                <dt className="text-muted-foreground">Qayerdan</dt>
                <dd>{document.location_name ?? '—'}</dd>
                <dt className="text-muted-foreground">Qayerga</dt>
                <dd>{document.destination_location_name ?? '—'}</dd>
                {document.note && (
                    <>
                        <dt className="text-muted-foreground">Izoh</dt>
                        <dd>{document.note}</dd>
                    </>
                )}
            </dl>
            <ExecutedLinesTable document={document} showPrice={false} />
            {document.status === 'executed' && (
                <div>
                    <Button variant="outline" asChild>
                        <Link
                            href={create({ query: { reverse: document.id } })}
                        >
                            <Undo2 />
                            Teskari ko'chirish
                        </Link>
                    </Button>
                </div>
            )}
        </div>
    );
}

function TransferForm({
    document,
    prefill,
    locations,
    products,
    stock,
}: {
    document: DocumentData | null;
    prefill: Prefill | null;
    locations: PickerOption[];
    products: PickerProduct[];
    stock: Record<number, Record<number, number>>;
}) {
    const { can } = usePermissions();
    const productsById = useMemo(
        () => new Map(products.map((product) => [product.id, product])),
        [products],
    );
    const source = document ?? prefill;

    const form = useForm({
        location_id: (source?.location_id ?? locations.at(0)?.id ?? '') as
            | number
            | '',
        destination_location_id: (source?.destination_location_id ?? '') as
            | number
            | '',
        note: document?.note ?? '',
        lines: (source?.lines ?? []).map((line): DraftLine => {
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

    const submit = (execute: boolean) => {
        form.transform((data) => ({
            location_id: data.location_id,
            destination_location_id: data.destination_location_id,
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
            form.put(TransferController.update.url(document.id), options);
        } else {
            form.post(TransferController.store.url(), options);
        }
    };

    const sourceStock = (line: DraftLine) =>
        `Qoldiq: ${formatQuantity(
            form.data.location_id === ''
                ? 0
                : (stock[form.data.location_id]?.[line.product_id] ?? 0),
            productsById.get(line.product_id)?.pieces_per_block ?? 1,
        )}`;

    return (
        <div className="flex flex-col gap-4">
            {document && <DocumentAudit document={document} />}
            {errors.document && <AlertError errors={[errors.document]} />}

            <div className="flex flex-wrap items-end gap-4">
                <LocationSelect
                    id="transfer-from"
                    label="Qayerdan"
                    value={form.data.location_id}
                    locations={locations}
                    error={errors.location_id}
                    onChange={(value) => form.setData('location_id', value)}
                />
                <LocationSelect
                    id="transfer-to"
                    label="Qayerga"
                    value={form.data.destination_location_id}
                    locations={locations}
                    error={errors.destination_location_id}
                    onChange={(value) =>
                        form.setData('destination_location_id', value)
                    }
                />
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
                    showPrice={false}
                    lineHint={sourceStock}
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
                    <Label htmlFor="transfer-note">Izoh (ixtiyoriy)</Label>
                    <Input
                        id="transfer-note"
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
                    showPrice={false}
                />
            </div>

            <DocumentFormActions
                processing={form.processing}
                cancelHref={index.url()}
                onSave={() => submit(false)}
                onExecute={
                    can('transfer.execute') ? () => submit(true) : undefined
                }
                onDelete={
                    document
                        ? () =>
                              router.delete(
                                  TransferController.destroy.url(document.id),
                              )
                        : undefined
                }
            />
        </div>
    );
}

function LocationSelect({
    id,
    label,
    value,
    locations,
    error,
    onChange,
}: {
    id: string;
    label: string;
    value: number | '';
    locations: PickerOption[];
    error?: string;
    onChange: (value: number | '') => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <select
                id={id}
                value={value}
                onChange={(event) => onChange(Number(event.target.value) || '')}
                className={cn(selectClassName, 'w-56')}
            >
                <option value="">Tanlang</option>
                {locations.map((location) => (
                    <option key={location.id} value={location.id}>
                        {location.name}
                    </option>
                ))}
            </select>
            <InputError message={error} />
        </div>
    );
}

TransferShow.layout = {
    breadcrumbs: [
        {
            title: "Ko'chirishlar",
            href: index(),
        },
    ],
};
