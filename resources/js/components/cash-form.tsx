import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { EnumOption } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

export const PERMANENT_WARNING =
    "Kassa operatsiyasini o'zgartirib yoki o'chirib bo'lmaydi, faqat teskari operatsiya bilan tuzatiladi. Saqlaysizmi?";

export const selectClassName =
    'h-9 w-full rounded-md border border-input bg-transparent px-2 text-sm';

type FieldErrors = Record<string, string | undefined>;

/**
 * The shared money form: a dialog with Summa, To'lov usuli and Izoh plus any extra fields,
 * used by Kassa (+ Kirim / + Chiqim) and the loan card. Cash operations are permanent (D14),
 * so Saqlash first asks for confirmation, then submits.
 */
export function CashOperationDialog({
    trigger,
    title,
    description,
    form,
    methods,
    amountName = 'amount',
    amountLabel = 'Summa',
    noteRequired = false,
    children,
}: {
    trigger: ReactNode;
    title: string;
    description?: string;
    form: RouteFormDefinition<'post'>;
    methods: EnumOption[];
    amountName?: string;
    amountLabel?: string;
    noteRequired?: boolean;
    children?: (errors: FieldErrors) => ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const idPrefix = `cash-${title.replace(/\W+/g, '-').toLowerCase()}`;

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);
                setConfirming(false);
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                {description && (
                    <DialogDescription>{description}</DialogDescription>
                )}
                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => {
                        setOpen(false);
                        setConfirming(false);
                    }}
                    onError={() => setConfirming(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div
                                className={cn(
                                    'space-y-4',
                                    confirming && 'hidden',
                                )}
                            >
                                {children?.(errors)}
                                <div className="grid gap-2">
                                    <Label htmlFor={`${idPrefix}-amount`}>
                                        {amountLabel}
                                    </Label>
                                    <Input
                                        id={`${idPrefix}-amount`}
                                        name={amountName}
                                        inputMode="decimal"
                                        required
                                        autoFocus
                                        placeholder="0"
                                    />
                                    <InputError message={errors[amountName]} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor={`${idPrefix}-method`}>
                                        To'lov usuli
                                    </Label>
                                    <select
                                        id={`${idPrefix}-method`}
                                        name="payment_method"
                                        defaultValue={methods[0]?.id}
                                        className={selectClassName}
                                    >
                                        {methods.map((method) => (
                                            <option
                                                key={method.id}
                                                value={method.id}
                                            >
                                                {method.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.payment_method}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor={`${idPrefix}-note`}>
                                        {noteRequired
                                            ? 'Izoh'
                                            : 'Izoh (ixtiyoriy)'}
                                    </Label>
                                    <Input
                                        id={`${idPrefix}-note`}
                                        name="note"
                                        required={noteRequired}
                                    />
                                    <InputError message={errors.note} />
                                </div>
                            </div>

                            {confirming && (
                                <p className="text-sm text-muted-foreground">
                                    {PERMANENT_WARNING}
                                </p>
                            )}

                            <DialogFooter className="gap-2">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() =>
                                        confirming
                                            ? setConfirming(false)
                                            : setOpen(false)
                                    }
                                >
                                    {confirming ? 'Orqaga' : 'Bekor qilish'}
                                </Button>
                                {confirming ? (
                                    <Button type="submit" disabled={processing}>
                                        Ha, saqlash
                                    </Button>
                                ) : (
                                    <Button
                                        type="button"
                                        onClick={(event) => {
                                            const formElement =
                                                event.currentTarget.form;

                                            if (formElement?.reportValidity()) {
                                                setConfirming(true);
                                            }
                                        }}
                                    >
                                        Saqlash
                                    </Button>
                                )}
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/** Money in green with "+", money out with "−". */
export function SignedAmount({
    direction,
    children,
}: {
    direction: 'in' | 'out';
    children: ReactNode;
}) {
    return (
        <span
            className={cn(
                'tabular-nums',
                direction === 'in'
                    ? 'text-green-700 dark:text-green-400'
                    : 'text-foreground',
            )}
        >
            {direction === 'in' ? '+' : '−'}
            {children}
        </span>
    );
}
