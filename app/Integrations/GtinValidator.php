<?php

declare(strict_types=1);

namespace App\Integrations;

final class GtinValidator
{
    public static function isValid(string $gtin): bool
    {
        if (preg_match('/^(?:\d{8}|\d{12,14})$/', $gtin) !== 1) {
            return false;
        }

        $checkDigit = (int) substr($gtin, -1);
        $digits = strrev(substr($gtin, 0, -1));
        $sum = 0;
        foreach (str_split($digits) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 3 : 1);
        }

        return (10 - ($sum % 10)) % 10 === $checkDigit;
    }
}
