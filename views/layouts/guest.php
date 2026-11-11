<?php

declare(strict_types=1);

/** @var string $content */
/** @var string $title */
/** @var string|null $flashSuccess */
/** @var string|null $flashError */
/** @var string|null $appName */
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> · <?= e($appName ?? 'Price Hunter') ?></title>
    <link rel="icon" href="<?= e(\App\Core\Url::to('/assets/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(\App\Core\Url::to('/assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="guest-body">
    <div class="guest-shell">
        <aside class="guest-brand">
            <a class="brand" href="<?= e(\App\Core\Url::to('/login')) ?>">
                <?php require __DIR__ . '/../partials/logo.php'; ?>
                <span>Price Hunter</span>
            </a>
            <div class="brand-copy">
                <p class="eyebrow">Monitoramento de preços</p>
                <h1>O menor custo confirmado, sem oferta inventada.</h1>
                <p>Cadastre produtos, compare lojas autorizadas e receba alerta quando o total chegar ao valor desejado.</p>
            </div>
            <ul class="brand-points">
                <li><i class="bi bi-shop"></i> Fontes independentes por marketplace</li>
                <li><i class="bi bi-truck"></i> Frete desconhecido não vira menor preço</li>
                <li><i class="bi bi-person-lock"></i> Cada usuário vê só os próprios dados</li>
            </ul>
        </aside>
        <main class="guest-main">
            <div class="guest-card">
                <?php require __DIR__ . '/../partials/flash.php'; ?>
                <?= $content ?>
            </div>
        </main>
    </div>
</body>
</html>
