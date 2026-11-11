<?php

declare(strict_types=1);

return [
    'host' => App\Core\Env::get('DB_HOST', '127.0.0.1'),
    'port' => App\Core\Env::get('DB_PORT', '3306'),
    'database' => App\Core\Env::get('DB_DATABASE', 'price_hunter'),
    'username' => App\Core\Env::get('DB_USERNAME', 'root'),
    'password' => App\Core\Env::get('DB_PASSWORD', ''),
    'charset' => App\Core\Env::get('DB_CHARSET', 'utf8mb4'),
];
