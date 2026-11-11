<?php

declare(strict_types=1);

return [
    'name' => App\Core\Env::get('APP_NAME', 'Price Hunter'),
    'env' => App\Core\Env::get('APP_ENV', 'local'),
    'url' => App\Core\Env::get('APP_URL', 'http://127.0.0.1:8090'),
    'debug' => App\Core\Env::bool('APP_DEBUG', false),
    'timezone' => App\Core\Env::get('APP_TIMEZONE', 'America/Sao_Paulo'),
];
