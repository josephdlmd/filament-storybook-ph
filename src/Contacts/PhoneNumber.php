<?php

namespace Josephdlmd\FilamentStorybookPh\Contacts;

/**
 * A Philippine phone number, typed any way the user knows it (0917 123 4567, +63 917-123-4567, (02) 8123 4567,
 * (032) 123 4567), in the one stored form: +63 917 123 4567, +63 2 8123 4567 or +63 32 123 4567 (owner's business
 * standard). Copy it into the app.
 */
class PhoneNumber
{
    /** The stored form, or null when it isn't a Philippine mobile or landline number. */
    public static function format(?string $typed): ?string
    {
        // The national number: without the country code 63 or the leading 0, so a typed "+63 0917" works too.
        $national = preg_replace('/^(63)?0?/', '', preg_replace('/\D/', '', (string) $typed));

        return match (true) {
            // Mobile: 9 and nine more digits.
            (bool) preg_match('/^9\d{9}$/', $national) => '+63 '.substr($national, 0, 3).' '.substr($national, 3, 3).' '.substr($national, 6),
            // Metro Manila landline: area code 2 and eight digits.
            (bool) preg_match('/^2\d{8}$/', $national) => '+63 2 '.substr($national, 1, 4).' '.substr($national, 5),
            // Provincial landline: a 2-digit area code and seven digits.
            (bool) preg_match('/^[3-8]\d{8}$/', $national) => '+63 '.substr($national, 0, 2).' '.substr($national, 2, 3).' '.substr($national, 5),
            default => null,
        };
    }
}
