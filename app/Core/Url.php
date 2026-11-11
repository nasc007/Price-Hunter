<?php

declare(strict_types=1);

namespace App\Core;

final class Url
{
    private static ?string $base = null;

    private static ?string $path = null;

    public static function base(): string
    {
        self::resolve();

        return self::$base ?? '';
    }

    public static function path(): string
    {
        self::resolve();

        return self::$path ?? '/';
    }

    public static function to(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return self::base() . $path;
    }

    private static function resolve(): void
    {
        if (self::$path !== null) {
            return;
        }

        $request = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $scriptDir = self::scriptDir();
        $prefix = '';

        if ($scriptDir !== '' && self::matchesPrefix($request, $scriptDir)) {
            $prefix = $scriptDir;
        } elseif (str_ends_with($scriptDir, '/public')) {
            $parent = substr($scriptDir, 0, -strlen('/public'));
            if ($parent !== '' && self::matchesPrefix($request, $parent)) {
                $prefix = $parent;
            }
        }

        $path = $prefix === '' ? $request : substr($request, strlen($prefix));
        if ($path === '' || $path === false) {
            $path = '/';
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        if ($path === '/index.php') {
            $path = '/';
        }

        self::$base = $prefix;
        self::$path = $path;
    }

    private static function scriptDir(): string
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = preg_replace('#/[^/]*$#', '', $script) ?? '';
        if ($dir === '/' || $dir === '.') {
            return '';
        }

        return $dir;
    }

    private static function matchesPrefix(string $request, string $prefix): bool
    {
        return $request === $prefix || str_starts_with($request, $prefix . '/');
    }
}
