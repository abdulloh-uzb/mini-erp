import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import OpeningBalanceController from '@/actions/App/Http/Controllers/OpeningBalanceController';
import AlertError from '@/components/alert-error';
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
import { SkipOpeningBalance } from '@/components/opening-balance-card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney, splitQuantity } from '@/lib/format';
import { dashboard } from '@/routes';
import { show } from '@/routes/opening-balance';
import type { DocumentData, PickerOption, PickerProduct } from '@/types';

type Props = {
    document: DocumentData | null;
    skipped: boolean;
    locations: PickerOption[];
    products: PickerProduct[];
};

const EXECUTE_WARNING =
    "Boshlang'ich qoldiq bir marta bajariladi. Bajarilgan hujjatni o'zgartirib yoki o'chirib bo'lmaydi. Davom etasizmi?";

export default function OpeningBalanceShow({
    document,
    skipped,
    locations,
    products,
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
                    <ExecutedOpeningBalance document={document} />
                ) : (
                    <OpeningBalanceForm
                        document={document}
                        locations={locations}
                        products={products}
                    />
                )}
            </div>
        </>
    );
}

function ExecutedOpeningBalance({ document }: { document: DocumentData }) {
    const total = document.lines.reduce(
        (sum, line) => sum + line.quantity * Number(line.price ?? 0),
        0,
    );

    return (
        <div className="flex flex-col gap-4">
            <DocumentAudit document={document} />
            <SectionTabs />
            <ExecutedLinesTable
                document={document}
                priceLabel="Tannarx/dona"
                showLocation
            />
            <p className="text-sm">
                Tovarlar qiymati:{' '}
                <span className="font-semibold">{formatMoney(total)}</span>
            </p>
            {document.note && (
                <p className="text-sm text-muted-foreground">
                    Izoh: {document.note}
                </p>
            )}
        </div>
    );
}

/** The opening balance's sections; Kassa, debts and loans tabs come with tickets 04, 05, 08. */
function SectionTabs() {
    return (
        <div className="inline-flex w-fit rounded-lg bg-muted p-1">
            <span className="rounded-md bg-background px-3 py-1.5 text-sm font-medium shadow-xs">
                Tovarlar
            </span>
        </div>
    );
}

function OpeningBalanceForm({
    document,
    locations,
    products,
}: {
    document: DocumentData | null;
    locations: PickerOption[];
    products: PickerProduct[];
}) {
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
    });
    const errors = form.errors as LineErrors;

    form.transform((data) => ({
        note: data.note,
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

            <SectionTabs />

            <div className="flex flex-wrap items-end gap-3">
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

            {form.data.lines.length === 0 ? (
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

OpeningBalanceShow.layout = {
    breadcrumbs: [
        {
            title: "Boshlang'ich qoldiq",
            href: show(),
        },
    ],
};
