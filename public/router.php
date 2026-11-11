<?php

declare(strict_types=1);

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Price Hunter requer PHP 8.2 ou superior. Atual: ' . PHP_VERSION;
    exit;
}

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$public = realpath(__DIR__);
$candidate = realpath(__DIR__ . $uri);

if ($public !== false && $candidate !== false && is_file($candidate)) {
    $prefix = rtrim($public, '\\/') . DIRECTORY_SEPARATOR;
    if (str_starts_with($candidate, $prefix)) {
        return false;
    }
}

require __DIR__ . '/index.php';
