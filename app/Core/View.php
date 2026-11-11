<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        $data['authUser'] ??= Auth::user();
        $data['title'] ??= 'Price Hunter';
        $data['errors'] ??= Session::errors();
        $data['flashSuccess'] = Session::pull('success');
        $data['flashError'] = Session::pull('error');
        $data['appName'] = Env::get('APP_NAME', 'Price Hunter');

        $viewFile = self::path($view);
        if (!is_file($viewFile)) {
            throw new \RuntimeException('View não encontrada: ' . $view);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        $layoutFile = self::path($layout);
        if (!is_file($layoutFile)) {
            throw new \RuntimeException('Layout não encontrado: ' . $layout);
        }

        require $layoutFile;
    }

    private static function path(string $view): string
    {
        return dirname(__DIR__, 2) . '/views/' . $view . '.php';
    }
}
