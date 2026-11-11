<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $path = dirname(__DIR__, 2) . '/storage/sessions';
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new \RuntimeException('Não foi possível preparar o diretório de sessão.');
        }

        session_save_path($path);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('ph_session');
        session_start();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }

    public static function pull(string $key): ?string
    {
        $value = $_SESSION['_flash_current'][$key] ?? null;
        unset($_SESSION['_flash_current'][$key]);

        return is_string($value) ? $value : null;
    }

    /**
     * @param array<string, string> $errors
     */
    public static function flashErrors(array $errors): void
    {
        $_SESSION['_errors'] = $errors;
    }

    /**
     * @return array<string, string>
     */
    public static function errors(): array
    {
        $errors = $_SESSION['_errors_current'] ?? [];
        if (!is_array($errors)) {
            return [];
        }

        $clean = [];
        foreach ($errors as $field => $message) {
            if (is_string($field) && is_string($message)) {
                $clean[$field] = $message;
            }
        }

        return $clean;
    }

    /**
     * @param array<string, scalar> $input
     */
    public static function flashInput(array $input): void
    {
        $clean = [];
        foreach ($input as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                continue;
            }
            if (in_array($key, ['senha', 'senha_atual', 'senha_confirmacao', '_csrf'], true)) {
                continue;
            }
            $clean[$key] = (string) $value;
        }

        $_SESSION['_old'] = $clean;
    }

    public static function old(string $key, string $default = ''): string
    {
        $current = $_SESSION['_old_current'] ?? [];
        if (!is_array($current) || !array_key_exists($key, $current)) {
            return $default;
        }

        return is_string($current[$key]) ? $current[$key] : $default;
    }

    public static function ageFlash(): void
    {
        $_SESSION['_old_current'] = is_array($_SESSION['_old'] ?? null) ? $_SESSION['_old'] : [];
        $_SESSION['_errors_current'] = is_array($_SESSION['_errors'] ?? null) ? $_SESSION['_errors'] : [];
        $_SESSION['_flash_current'] = is_array($_SESSION['_flash'] ?? null) ? $_SESSION['_flash'] : [];
        unset($_SESSION['_old'], $_SESSION['_errors'], $_SESSION['_flash']);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        self::regenerate();
    }
}
