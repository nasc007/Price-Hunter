<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Url;

/** @var string $content */
/** @var string $title */
/** @var string|null $flashSuccess */
/** @var string|null $flashError */
/** @var array<string, mixed>|null $authUser */

$nome = (string) ($authUser['nome'] ?? 'Usuário');
$inicial = mb_strtoupper(mb_substr($nome, 0, 1));
$produtosAtivo = str_starts_with(Url::path(), '/produtos');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> · Price Hunter</title>
    <link rel="icon" href="<?= e(Url::to('/assets/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(Url::to('/assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="app-body">
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <a class="brand brand-light" href="<?= e(Url::to('/')) ?>">
                <?php require __DIR__ . '/../partials/logo.php'; ?>
                <span>Price Hunter</span>
            </a>
            <nav class="side-nav" aria-label="Principal">
                <p class="nav-label">Monitorar</p>
                <a class="nav-link<?= nav_active('/') ?>" href="<?= e(Url::to('/')) ?>"<?= nav_active('/') !== '' ? ' aria-current="page"' : '' ?>>
                    <i class="bi bi-grid"></i> Painel
                </a>
                <a class="nav-link<?= $produtosAtivo ? ' is-active' : '' ?>" href="<?= e(Url::to('/produtos')) ?>"<?= $produtosAtivo ? ' aria-current="page"' : '' ?>>
                    <i class="bi bi-box-seam"></i> Produtos
                </a>
                <a class="nav-link<?= str_starts_with(Url::path(), '/produtos/') && str_ends_with(Url::path(), '/comparar') ? ' is-active' : '' ?>" href="<?= e(Url::to('/produtos')) ?>">
                    <i class="bi bi-tags"></i> Comparar preços
                </a>
                <p class="nav-label">Análise</p>
                <span class="nav-link is-disabled" title="Histórico e gráficos na Fase 6">
                    <i class="bi bi-graph-up"></i> Histórico <small>Fase 6</small>
                </span>
                <span class="nav-link is-disabled" title="Alertas na Fase 8">
                    <i class="bi bi-bell"></i> Alertas <small>Fase 8</small>
                </span>
                <p class="nav-label">Conta</p>
                <a class="nav-link<?= nav_active('/perfil') ?>" href="<?= e(Url::to('/perfil')) ?>"<?= nav_active('/perfil') !== '' ? ' aria-current="page"' : '' ?>>
                    <i class="bi bi-person"></i> Perfil
                </a>
            </nav>
            <form method="post" action="<?= e(Url::to('/sair')) ?>" class="logout-form">
                <?= Csrf::field() ?>
                <button type="submit" class="nav-link nav-button">
                    <i class="bi bi-box-arrow-right"></i> Sair
                </button>
            </form>
        </aside>
        <div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>
        <div class="app-main">
            <header class="topbar">
                <button class="icon-btn" id="openSidebar" type="button" aria-label="Abrir menu">
                    <i class="bi bi-list"></i>
                </button>
                <div class="topbar-title"><?= e($title) ?></div>
                <div class="user-chip">
                    <span class="avatar"><?= e($inicial) ?></span>
                    <span class="user-name"><?= e($nome) ?></span>
                </div>
            </header>
            <main class="page">
                <?php require __DIR__ . '/../partials/flash.php'; ?>
                <?= $content ?>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= e(Url::to('/assets/js/app.js')) ?>"></script>
</body>
</html>
