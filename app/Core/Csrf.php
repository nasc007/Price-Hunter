<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        $current = $_SESSION['_csrf'] ?? '';
        if (!is_string($current) || $current === '') {
            $current = bin2hex(random_bytes(32));
            $_SESSION['_csrf'] = $current;
        }

        return $current;
    }

    public static function verify(?string $token): bool
    {
        $known = $_SESSION['_csrf'] ?? '';
        if (!is_string($known) || $known === '' || !is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($known, $token);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '">';
    }
}
