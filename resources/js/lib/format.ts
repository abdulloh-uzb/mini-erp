/**
 * Number formats from the spec (story 115): money as "1 250 000 so'm" and quantities
 * as "4 blok + 3 dona".
 */
const moneyFormatter = new Intl.NumberFormat('ru-RU', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});

export function formatMoney(
    amount: string | number | null | undefined,
): string {
    const value = Number(amount ?? 0);

    return `${moneyFormatter.format(value).replace(/\s/g, ' ')} so'm`;
}

/**
 * What a per-piece price comes to for a whole block, "1 blok = 78 000 so'm"; null for a
 * product sold only by the piece or an empty price. Prices are always stored per piece.
 */
export function formatBlockPrice(
    price: string | number | null | undefined,
    piecesPerBlock: number,
): string | null {
    if (
        piecesPerBlock <= 1 ||
        price === null ||
        price === undefined ||
        price === ''
    ) {
        return null;
    }

    return `1 blok = ${formatMoney(Number(price) * piecesPerBlock)}`;
}

export function splitQuantity(
    pieces: number,
    piecesPerBlock: number,
): { blocks: number; pieces: number } {
    if (piecesPerBlock <= 1) {
        return { blocks: 0, pieces };
    }

    return {
        blocks: Math.floor(pieces / piecesPerBlock),
        pieces: pieces % piecesPerBlock,
    };
}

export function formatQuantity(pieces: number, piecesPerBlock: number): string {
    if (piecesPerBlock <= 1) {
        return `${pieces} dona`;
    }

    const split = splitQuantity(pieces, piecesPerBlock);

    if (split.blocks === 0) {
        return `${split.pieces} dona`;
    }

    if (split.pieces === 0) {
        return `${split.blocks} blok`;
    }

    return `${split.blocks} blok + ${split.pieces} dona`;
}

export function formatDateTime(value: string | null): string {
    if (value === null) {
        return '—';
    }

    return new Date(value).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/** A calendar date ("2026-12-31") as "31.12.2026". */
export function formatDate(value: string | null): string {
    if (value === null) {
        return '—';
    }

    const [year, month, day] = value.split('-');

    return `${day}.${month}.${year}`;
}

/** Payment method values as the Uzbek labels the cash register shows. */
export const paymentMethodLabels: Record<string, string> = {
    cash: 'Naqd',
    card: 'Karta',
    bank_transfer: "Bank o'tkazmasi",
};
