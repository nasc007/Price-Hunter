<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var list<array{method: string, path: string, handler: array{0: class-string, 1: string}, middleware: list<class-string>}> */
    private array $routes = [];

    /** @param array{0: class-string, 1: string} $handler
     *  @param list<class-string> $middleware */
    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    /** @param array{0: class-string, 1: string} $handler
     *  @param list<class-string> $middleware */
    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /** @param array{0: class-string, 1: string} $handler
     *  @param list<class-string> $middleware */
    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $allowed = [];

        foreach ($this->routes as $route) {
            $params = $this->match($route['path'], $path);
            if ($params === null) {
                continue;
            }

            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }

            $handler = $route['handler'];
            $pipeline = array_reduce(
                array_reverse($route['middleware']),
                static function (callable $next, string $middleware) use ($params): callable {
                    return static function () use ($middleware, $next, $params): void {
                        $instance = new $middleware();
                        $instance->handle($next, $params);
                    };
                },
                static function () use ($handler, $params): void {
                    [$class, $action] = $handler;
                    $controller = new $class();
                    $controller->{$action}(...array_values($params));
                }
            );

            $pipeline();
            return;
        }

        if ($allowed !== []) {
            http_response_code(405);
            header('Allow: ' . implode(', ', array_unique($allowed)));
            View::render('errors/404', [
                'title' => 'Método não permitido',
                'message' => 'Esta ação não pode ser chamada desta forma.',
            ], Auth::check() ? 'layouts/app' : 'layouts/guest');
            return;
        }

        http_response_code(404);
        View::render('errors/404', [
            'title' => 'Página não encontrada',
            'message' => 'O endereço não existe neste sistema.',
        ], Auth::check() ? 'layouts/app' : 'layouts/guest');
    }

    /** @return array<string, string>|null */
    private function match(string $routePath, string $path): ?array
    {
        $pattern = preg_replace('#\{([A-Za-z_][A-Za-z0-9_]*)\}#', '(?P<$1>[^/]+)', $routePath);
        if (!is_string($pattern) || preg_match('#^' . $pattern . '$#', $path, $matches) !== 1) {
            return null;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }
}
