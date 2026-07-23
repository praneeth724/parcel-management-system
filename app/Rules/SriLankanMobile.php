<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a Sri Lankan mobile number, as required by the specification.
 *
 * Accepts the three forms people actually type:
 *   0771234567        (local)
 *   +94771234567      (international)
 *   94771234567       (international, no plus)
 *
 * Separators (spaces, dashes, brackets) are ignored. Landline numbers are
 * rejected: only the mobile prefixes issued by Sri Lankan operators pass.
 */
class SriLankanMobile implements ValidationRule
{
    /**
     * Mobile prefixes in use: Dialog, Mobitel, Etisalat/Hutch and Airtel.
     *
     * @var array<int, string>
     */
    private const MOBILE_PREFIXES = ['70', '71', '72', '74', '75', '76', '77', '78'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_numeric($value)) {
            $fail('The :attribute must be a valid Sri Lankan mobile number.');

            return;
        }

        $digits = self::digitsOnly((string) $value);
        $national = self::toNationalDigits($digits);

        if ($national === null) {
            $fail('The :attribute must be a valid Sri Lankan mobile number, for example 0771234567.');

            return;
        }

        if (! in_array(substr($national, 1, 2), self::MOBILE_PREFIXES, strict: true)) {
            $fail('The :attribute must start with a valid Sri Lankan mobile prefix (070, 071, 072, 074, 075, 076, 077 or 078).');
        }
    }

    /**
     * Reduce any accepted format to the canonical 10-digit local form
     * (0771234567), or null when the input cannot be one.
     */
    public static function toNationalDigits(string $digits): ?string
    {
        // +94771234567 / 94771234567 -> 0771234567
        if (str_starts_with($digits, '94') && strlen($digits) === 11) {
            return '0'.substr($digits, 2);
        }

        // 771234567 (leading zero omitted) -> 0771234567
        if (strlen($digits) === 9 && $digits[0] !== '0') {
            return '0'.$digits;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return $digits;
        }

        return null;
    }

    /**
     * Normalise user input for storage. Returns the input unchanged when it is
     * not a recognisable Sri Lankan number, leaving the rule to reject it.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::toNationalDigits(self::digitsOnly($value)) ?? trim($value);
    }

    private static function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
