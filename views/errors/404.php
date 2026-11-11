<?php

declare(strict_types=1);

/** @var string $message */
?>
<p class="eyebrow">404</p>
<h2><?= e($title ?? 'Página não encontrada') ?></h2>
<p class="lead"><?= e($message ?? 'O endereço não existe neste sistema.') ?></p>
<p class="auth-switch"><a href="<?= e(\App\Core\Url::to('/')) ?>">Voltar ao painel</a></p>
