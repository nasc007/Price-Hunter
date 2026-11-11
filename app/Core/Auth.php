<?php

declare(strict_types=1);

namespace App\Core;

use App\Helpers\Logger;
use App\Models\Usuario;

final class Auth
{
    private const MAX_ATTEMPTS = 5;

    private const LOCK_SECONDS = 600;

    /** @var array<string, mixed>|null */
    private static ?array $user = null;

    public static function check(): bool
    {
        return self::id() !== null && self::user() !== null;
    }

    public static function id(): ?int
    {
        $id = $_SESSION['usuario_id'] ?? null;
        if (is_int($id)) {
            return $id;
        }
        if (is_string($id) && ctype_digit($id)) {
            return (int) $id;
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }

        $id = self::id();
        if ($id === null) {
            return null;
        }

        $user = Usuario::findById($id);
        if ($user === null || (int) $user['ativo'] !== 1) {
            self::logout();
            return null;
        }

        self::$user = $user;

        return $user;
    }

    public static function secondsLocked(): int
    {
        $until = (int) ($_SESSION['_login_lock'] ?? 0);

        return max(0, $until - time());
    }

    public static function attempt(string $email, string $password): ?string
    {
        if (self::secondsLocked() > 0) {
            return 'Muitas tentativas seguidas. Aguarde alguns minutos e tente novamente.';
        }

        $user = Usuario::findByEmail($email);
        $hash = is_array($user) ? (string) ($user['senha_hash'] ?? '') : '';
        $valid = is_array($user) && (int) ($user['ativo'] ?? 0) === 1 && $hash !== '' && password_verify($password, $hash);

        if (!$valid) {
            $attempts = (int) ($_SESSION['_login_attempts'] ?? 0) + 1;
            $_SESSION['_login_attempts'] = $attempts;
            if ($attempts >= self::MAX_ATTEMPTS) {
                $_SESSION['_login_lock'] = time() + self::LOCK_SECONDS;
                $_SESSION['_login_attempts'] = 0;
            }

            Logger::warning('Falha de login.', ['email' => mb_strtolower(trim($email))]);

            return 'E-mail ou senha inválidos.';
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            Usuario::atualizarSenha((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        unset($user['senha_hash']);
        self::login($user);

        return null;
    }

    /** @param array<string, mixed> $user */
    public static function login(array $user): void
    {
        self::regenerate();
        $_SESSION['usuario_id'] = (int) $user['id'];
        unset($_SESSION['_login_attempts'], $_SESSION['_login_lock'], $user['senha_hash']);
        self::$user = $user;
        Usuario::registrarLogin((int) $user['id']);
    }

    public static function logout(): void
    {
        self::$user = null;
        unset($_SESSION['usuario_id']);
        Session::destroy();
    }

    public static function refresh(): void
    {
        self::$user = null;
    }

    private static function regenerate(): void
    {
        Session::regenerate();
    }
}
