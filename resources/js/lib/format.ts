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
