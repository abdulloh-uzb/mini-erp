const PREFIX = '+998';

/**
 * Format typed digits as "+998 90 123 45 67", the mask "+998 __ ___ __ __".
 */
export function formatPhone(value: string): string {
    const trimmed = value.trim();
    let digits = trimmed.replace(/\D/g, '');

    // Strip the country code only when it is really there: the "+998" the mask
    // shows (even half-deleted), or a pasted full number of 12 digits.
    if (trimmed.startsWith('+')) {
        digits = digits.slice(Math.min(3, digits.length));
    } else if (digits.length === 12 && digits.startsWith('998')) {
        digits = digits.slice(3);
    }

    digits = digits.slice(0, 9);

    const parts = [
        digits.slice(0, 2),
        digits.slice(2, 5),
        digits.slice(5, 7),
        digits.slice(7, 9),
    ].filter((part) => part !== '');

    return [PREFIX, ...parts].join(' ');
}
