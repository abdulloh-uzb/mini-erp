import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import AlertError from '@/components/alert-error';
import { selectClassName } from '@/components/cash-form';
import {
    DocumentAudit,
    DocumentFormActions,
    DocumentStatusBadge,
    ExecutedLinesTable,
    QuantityInput,
} from '@/components/document-form';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import {
    formatDateTime,
    formatMoney,
    formatQuantity,
    splitQuantity,
} from '@/lib/format';
import { returnKinds } from '@/lib/returns';
import type { ReturnKind } from '@/lib/returns';
import { cn } from '@/lib/utils';
import type { DocumentData, EnumOption, PickerOption } from '@/types';

/** A source line beside what it moved, what executed returns gave back and what is left. */
type ReturnLine = {
    source_line_id: number;
    product_name: string;
    pieces_per_block: number;
    price: string | null;
    sold: number;
    returned: number;
    returnable: number;
    /** Pieces this draft gives back. */
    quantity: number;
};

type Props = {
    kind: ReturnKind;
    document: DocumentData | null;
    editable: boolean;
    source: {
        id: number;
        number: number;
        counterparty_name: string | null;
        location_name: string | null;
        executed_at: string | null;
    };
    lines: ReturnLine[];
    default_location_id: number | null;
    default_refund_method: string;
    /** The counterparty's current debt on the return's side; null without a counterparty. */
    counterparty_debt: string | null;
    /** How an executed return's value went back. */
    settlement: {
        debt_reduced: string;
        refunded: string;
        method: string | null;
    } | null;
    locations: PickerOption[];
    methods: EnumOption[];
};

type FieldErrors = Record<string, string | undefined>;

export default function ReturnShow(props: Props) {
    const { kind, document, editable, source } = props;
    const config = returnKinds[kind];
    const title = document
        ? `${config.single} №${document.number}`
        : `Yangi qaytarish`;
    setLayoutProps({
        breadcrumbs: [{ title: config.title, href: config.controller.index() }],
    });

    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading title={title} description={config.description} />
                    {document && (
                        <DocumentStatusBadge status={document.status} />
                    )}
                </div>

                <dl className="grid max-w-xl grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                    <dt className="text-muted-foreground">Manba</dt>
                    <dd>
                        <Link
                            href={config.sourceShow(source.id)}
                            className="underline underline-offset-4"
                        >
                            {config.source} №{source.number}
                        </Link>
                        , {formatDateTime(source.executed_at)}
                    </dd>
                    <dt className="text-muted-foreground">
                        {config.counterparty}
                    </dt>
                    <dd>{source.counterparty_name ?? '—'}</dd>
                </dl>

                {editable ? (
                    <ReturnForm {...props} />
                ) : (
                    document && (
                        <ReadOnlyReturn
                            document={document}
                            settlement={props.settlement}
                            refundLabel={config.refundLabel}
                        />
                    )
                )}
            </div>
        </>
    );
}

function ReadOnlyReturn({
    document,
    settlement,
    refundLabel,
}: {
    document: DocumentData;
    settlement: Props['settlement'];
    refundLabel: string;
}) {
    const total = document.lines.reduce(
        (sum, line) => sum + line.quantity * Number(line.price ?? 0),
        0,
    );

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
            <ExecutedLinesTable document={document} />
            {settlement && (
                <dl className="grid w-full max-w-xs gap-2 rounded-xl border p-4 text-sm">
                    <SummaryRow
                        label="Jami"
                        value={formatMoney(total)}
                        strong
                    />
                    <SummaryRow
                        label="Qarzdan kamaydi"
                        value={formatMoney(settlement.debt_reduced)}
                    />
                    <SummaryRow
                        label={
                            settlement.method
                                ? `${refundLabel} (${settlement.method})`
                                : refundLabel
                        }
                        value={formatMoney(settlement.refunded)}
                    />
                </dl>
            )}
        </div>
    );
}

function ReturnForm({
    kind,
    document,
    source,
    lines,
    default_location_id: defaultLocationId,
    default_refund_method: defaultRefundMethod,
    counterparty_debt: counterpartyDebt,
    locations,
    methods,
}: Props) {
    const config = returnKinds[kind];
    const { can } = usePermissions();
    const canExecute = can(config.executePermission);

    const form = useForm({
        location_id: (defaultLocationId ?? '') as number | '',
        refund_method: defaultRefundMethod,
        note: document?.note ?? '',
        lines: lines.map((line) => {
            const split = splitQuantity(line.quantity, line.pieces_per_block);

            return {
                source_line_id: line.source_line_id,
                blocks: line.quantity === 0 ? '' : String(split.blocks),
                pieces: line.quantity === 0 ? '' : String(split.pieces),
            };
        }),
    });
    const errors = form.errors as FieldErrors;

    const pieces = (index: number) =>
        (Number(form.data.lines[index].blocks) || 0) *
            lines[index].pieces_per_block +
        (Number(form.data.lines[index].pieces) || 0);
    const total = lines.reduce(
        (sum, line, index) => sum + pieces(index) * Number(line.price ?? 0),
        0,
    );
    const fromDebt = Math.min(
        total,
        Math.max(Number(counterpartyDebt ?? 0), 0),
    );
    const refund = total - fromDebt;

    const updateLine = (
        index: number,
        changes: Partial<(typeof form.data.lines)[number]>,
    ) =>
        form.setData(
            'lines',
            form.data.lines.map((line, position) =>
                position === index ? { ...line, ...changes } : line,
            ),
        );

    const submit = (execute: boolean) => {
        form.transform((data) => ({
            source_document_id: source.id,
            location_id: data.location_id,
            refund_method: data.refund_method,
            note: data.note === '' ? null : data.note,
            lines: data.lines.map((line) => ({
                source_line_id: line.source_line_id,
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
            form.put(config.controller.update.url(document.id), options);
        } else {
            form.post(config.controller.store.url(), options);
        }
    };

    return (
        <div className="flex flex-col gap-4">
            {document && <DocumentAudit document={document} />}
            {errors.document && <AlertError errors={[errors.document]} />}

            <div className="grid gap-2">
                <Label htmlFor="return-location">Joy</Label>
                <select
                    id="return-location"
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

            <div className="overflow-x-auto rounded-xl border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            {[
                                'Mahsulot',
                                config.moved,
                                'Qaytarilgan',
                                'Qaytarish mumkin',
                                'Blok',
                                'Dona',
                                'Narx/dona',
                                'Summa',
                            ].map((column) => (
                                <th
                                    key={column}
                                    className="px-3 py-2 font-medium"
                                >
                                    {column}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {lines.map((line, index) => {
                            const quantity = (value: number) =>
                                formatQuantity(value, line.pieces_per_block);

                            return (
                                <tr
                                    key={line.source_line_id}
                                    className="border-t align-top"
                                >
                                    <td className="px-3 py-2">
                                        <div className="font-medium">
                                            {line.product_name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            1 blok = {line.pieces_per_block}{' '}
                                            dona
                                        </div>
                                    </td>
                                    <td className="px-3 py-2 whitespace-nowrap tabular-nums">
                                        {quantity(line.sold)}
                                    </td>
                                    <td className="px-3 py-2 whitespace-nowrap tabular-nums">
                                        {quantity(line.returned)}
                                    </td>
                                    <td className="px-3 py-2 whitespace-nowrap tabular-nums">
                                        {quantity(line.returnable)}
                                    </td>
                                    <td className="px-3 py-2">
                                        <QuantityInput
                                            label="Blok"
                                            value={
                                                form.data.lines[index].blocks
                                            }
                                            onChange={(blocks) =>
                                                updateLine(index, { blocks })
                                            }
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <QuantityInput
                                            label="Dona"
                                            value={
                                                form.data.lines[index].pieces
                                            }
                                            onChange={(pieces) =>
                                                updateLine(index, { pieces })
                                            }
                                        />
                                        <InputError
                                            message={
                                                errors[`lines.${index}.pieces`]
                                            }
                                        />
                                    </td>
                                    <td className="px-3 py-2 whitespace-nowrap tabular-nums">
                                        {formatMoney(line.price)}
                                    </td>
                                    <td className="px-3 py-2 whitespace-nowrap tabular-nums">
                                        {formatMoney(
                                            pieces(index) *
                                                Number(line.price ?? 0),
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            <InputError message={errors.lines} />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="grid w-full max-w-md gap-2">
                    <Label htmlFor="return-note">Izoh (ixtiyoriy)</Label>
                    <Input
                        id="return-note"
                        value={form.data.note}
                        onChange={(event) =>
                            form.setData('note', event.target.value)
                        }
                    />
                    <InputError message={errors.note} />
                </div>
                <div className="grid w-full max-w-xs gap-3 rounded-xl border p-4 text-sm">
                    <SummaryRow
                        label="Jami"
                        value={formatMoney(total)}
                        strong
                    />
                    {counterpartyDebt !== null && (
                        <SummaryRow
                            label={`Qarzdan kamayadi (qarz: ${formatMoney(counterpartyDebt)})`}
                            value={formatMoney(fromDebt)}
                        />
                    )}
                    <SummaryRow
                        label={config.refundLabel}
                        value={formatMoney(refund)}
                        strong
                    />
                    {refund > 0 && (
                        <div className="grid gap-1">
                            <Label htmlFor="return-refund-method">
                                To'lov usuli
                            </Label>
                            <select
                                id="return-refund-method"
                                value={form.data.refund_method}
                                onChange={(event) =>
                                    form.setData(
                                        'refund_method',
                                        event.target.value,
                                    )
                                }
                                className={selectClassName}
                            >
                                {methods.map((method) => (
                                    <option key={method.id} value={method.id}>
                                        {method.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                    <InputError message={errors.refund_method} />
                    <p className="text-xs text-muted-foreground">
                        Bajarilganda qarz qayta hisoblanadi.
                    </p>
                </div>
            </div>

            {!canExecute && document && (
                <p className="text-sm text-muted-foreground">
                    Egasi bajarishini kutmoqda.
                </p>
            )}

            <DocumentFormActions
                processing={form.processing}
                cancelHref={config.controller.index.url()}
                onSave={() => submit(false)}
                onExecute={canExecute ? () => submit(true) : undefined}
                onDelete={
                    document
                        ? () =>
                              router.delete(
                                  config.controller.destroy.url(document.id),
                              )
                        : undefined
                }
            />
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
            <dd className={cn('tabular-nums', strong && 'font-semibold')}>
                {value}
            </dd>
        </div>
    );
}
