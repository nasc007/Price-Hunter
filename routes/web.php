<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\PerfilController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;

return static function (Router $router): void {
    $router->get('/login', [AuthController::class, 'formularioLogin'], [GuestMiddleware::class]);
    $router->post('/login', [AuthController::class, 'entrar'], [GuestMiddleware::class]);
    $router->get('/cadastro', [AuthController::class, 'formularioCadastro'], [GuestMiddleware::class]);
    $router->post('/cadastro', [AuthController::class, 'cadastrar'], [GuestMiddleware::class]);
    $router->post('/sair', [AuthController::class, 'sair'], [AuthMiddleware::class]);

    $router->get('/', [DashboardController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/perfil', [PerfilController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/perfil', [PerfilController::class, 'atualizar'], [AuthMiddleware::class]);
    $router->post('/perfil/senha', [PerfilController::class, 'senha'], [AuthMiddleware::class]);
};
