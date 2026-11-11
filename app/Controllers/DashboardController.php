<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Painel;

final class DashboardController
{
    public function index(): void
    {
        $usuarioId = Auth::id();
        if ($usuarioId === null) {
            return;
        }

        View::render('dashboard/index', [
            'title' => 'Painel',
            'resumo' => Painel::resumo($usuarioId),
            'menores' => Painel::menoresPrecos($usuarioId),
            'alteracoes' => Painel::alteracoesRecentes($usuarioId),
            'fontes' => Painel::fontes(),
        ]);
    }
}
