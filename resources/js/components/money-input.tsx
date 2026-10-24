import type { ComponentProps } from 'react';
import { useLayoutEffect, useRef } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/**
 * Turns whatever was typed or pasted into an amount the server accepts: digits with at most
 * one decimal point (a comma counts as one) and two decimals. Spaces, letters and leading
 * zeros are dropped.
 */
export function normalizeMoney(typed: string): string {
    const cleaned = typed.replace(/,/g, '.').replace(/[^\d.]/g, '');
    const [whole, ...decimals] = cleaned.split('.');
    const integer = whole.replace(/^0+(?=\d)/, '');

    if (decimals.length === 0) {
        return integer;
    }

    return `${integer === '' ? '0' : integer}.${decimals.join('').slice(0, 2)}`;
}

/** "1260000.5" as "1 260 000.5", keeping a trailing point while it is being typed. */
function groupDigits(amount: string): string {
    const [whole, decimals] = amount.split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

    return decimals === undefined ? grouped : `${grouped}.${decimals}`;
}

/**
 * A money field showing thousands apart ("1 260 000") while its value stays the plain amount
 * ("1260000"). The caret keeps its place among the digits as the spaces move.
 */
export function MoneyInput({
    value,
    onValueChange,
    className,
    ...props
}: Omit<ComponentProps<typeof Input>, 'value' | 'onChange' | 'type'> & {
    value: string;
    onValueChange: (amount: string) => void;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const digitsBeforeCaret = useRef<number | null>(null);
    const display = groupDigits(value);

    useLayoutEffect(() => {
        const input = inputRef.current;

        if (!input || digitsBeforeCaret.current === null) {
            return;
        }

        let caret = 0;
        let digits = 0;

        while (caret < display.length && digits < digitsBeforeCaret.current) {
            if (display[caret] !== ' ') {
                digits++;
            }

            caret++;
        }

        input.setSelectionRange(caret, caret);
        digitsBeforeCaret.current = null;
    }, [display]);

    return (
        <Input
            ref={inputRef}
            inputMode="decimal"
            autoComplete="off"
            value={display}
            onChange={(event) => {
                const typed = event.target.value;
                const caret = event.target.selectionStart ?? typed.length;
                const amount = normalizeMoney(typed);
                digitsBeforeCaret.current = normalizeMoney(
                    typed.slice(0, caret),
                ).length;
                onValueChange(amount);
            }}
            className={cn('text-right tabular-nums', className)}
            {...props}
        />
    );
}
