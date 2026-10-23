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

        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) === 9) {
            $digits = '998'.$digits;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '998')) {
            return '+'.$digits;
        }

        return trim($phone);
    }

    /**
     * Get the validation rules for a normalised phone number.
     *
     * @return array<int, string>
     */
    public static function rules(): array
    {
        return ['required', 'string', 'regex:/^\+998\d{9}$/'];
    }
}
