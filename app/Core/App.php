<?php

declare(strict_types=1);

namespace App\Core;

final class App
{
    public function run(): void
    {
        $this->headers();

        if (Request::method() === 'POST' && !Csrf::verify(Request::input('_csrf'))) {
            Session::flash('error', 'A sessão expirou. Atualize a página e envie novamente.');
            Redirect::to(Auth::check() ? '/' : '/login');
        }

        $router = new Router();
        $register = require dirname(__DIR__, 2) . '/routes/web.php';
        $register($router);
        $router->dispatch(Request::method(), Url::path());
    }

    private function headers(): void
    {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cache-Control: no-store, private');
        header(
            "Content-Security-Policy: default-src 'self'; "
            . "img-src 'self' data: https:; "
            . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
            . "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; "
            . "script-src 'self' https://cdn.jsdelivr.net; "
            . "connect-src 'self'; "
            . "frame-ancestors 'self'; "
            . "base-uri 'self'; "
            . "form-action 'self'"
        );
    }
}
