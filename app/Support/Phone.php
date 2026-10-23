<?php

namespace App\Support;

/**
 * Uzbek mobile phone numbers, stored as +998XXXXXXXXX.
 */
class Phone
{
    /**
     * Normalise any common spelling ("+998 90 123 45 67", "998901234567", "90 123-45-67")
     * to +998XXXXXXXXX. Input that is not an Uzbek number is returned trimmed and unchanged,
     * so validation can reject it.
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = self::digits($phone);

        if (strlen($digits) === 9) {
            $digits = '998'.$digits;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '998')) {
            return '+'.$digits;
        }

        return trim($phone);
    }

    /**
     * Get only the digits of a typed phone number or search term.
     */
    public static function digits(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }

    /**
     * Determine if an optional phone was left empty: nothing typed, or only the input mask's "+998".
     */
    public static function isBlank(?string $phone): bool
    {
        return in_array(self::digits($phone ?? ''), ['', '998'], true);
    }

    /**
     * Get the validation rules for a normalised phone number.
     *
     * @return array<int, string>
     */
    public static function rules(): array
    {
        return ['required', ...self::formatRules()];
    }

    /**
     * Get the validation rules for an optional normalised phone number.
     *
     * @return array<int, string>
     */
    public static function optionalRules(): array
    {
        return ['nullable', ...self::formatRules()];
    }

    /**
     * @return array<int, string>
     */
    private static function formatRules(): array
    {
        return ['string', 'regex:/^\+998\d{9}$/'];
    }
}
