<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Redirect;

final class AuthMiddleware
{
    /** @param array<string, string> $params */
    public function handle(callable $next, array $params = []): void
    {
        unset($params);
        if (!Auth::check()) {
            Redirect::to('/login');
        }

        $next();
    }
}
