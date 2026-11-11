<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\Session;
use App\Helpers\Logger;

$root = dirname(__DIR__);

Env::load($root . '/.env');

date_default_timezone_set(Env::get('APP_TIMEZONE', 'America/Sao_Paulo') ?? 'America/Sao_Paulo');

$debug = Env::bool('APP_DEBUG', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('default_charset', 'UTF-8');

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @param array<string, string> $errors
 */
function field_error(array $errors, string $field): string
{
    if (!isset($errors[$field]) || $errors[$field] === '') {
        return '';
    }

    return '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>';
}

/**
 * @param array<string, string> $errors
 */
function invalid_class(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

function nav_active(string $path): string
{
    return App\Core\Url::path() === $path ? ' is-active' : '';
}

Session::start();
Session::ageFlash();

set_exception_handler(static function (Throwable $e) use ($debug): void {
    Logger::error($e->getMessage(), [
        'type' => $e::class,
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    if ($debug) {
        echo '<h1>Erro interno</h1><pre>' . e($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine()) . '</pre>';
        return;
    }

    echo 'Ocorreu um erro interno. Tente novamente em instantes.';
});
