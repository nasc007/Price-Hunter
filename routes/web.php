<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\PerfilController;
use App\Controllers\ProdutoController;
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
    $router->get('/produtos', [ProdutoController::class, 'index'], [AuthMiddleware::class]);
    $router->get('/produtos/novo', [ProdutoController::class, 'novo'], [AuthMiddleware::class]);
    $router->post('/produtos', [ProdutoController::class, 'criar'], [AuthMiddleware::class]);
    $router->get('/produtos/{id}/editar', [ProdutoController::class, 'editar'], [AuthMiddleware::class]);
    $router->post('/produtos/{id}', [ProdutoController::class, 'atualizar'], [AuthMiddleware::class]);
    $router->post('/produtos/{id}/status', [ProdutoController::class, 'alterarStatus'], [AuthMiddleware::class]);
    $router->post('/produtos/{id}/excluir', [ProdutoController::class, 'excluir'], [AuthMiddleware::class]);
    $router->get('/perfil', [PerfilController::class, 'index'], [AuthMiddleware::class]);
    $router->post('/perfil', [PerfilController::class, 'atualizar'], [AuthMiddleware::class]);
    $router->post('/perfil/senha', [PerfilController::class, 'senha'], [AuthMiddleware::class]);
};
