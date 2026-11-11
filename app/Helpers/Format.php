<?php

declare(strict_types=1);

namespace App\Helpers;

final class Format
{
    public static function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    public static function dateTime(?string $value, string $empty = 'Nenhuma consulta'): string
    {
        if ($value === null || $value === '') {
            return $empty;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
        if ($date === false) {
            return $value;
        }

        return $date->format('d/m/Y H:i');
    }
}
