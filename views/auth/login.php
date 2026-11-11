<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Session;
use App\Core\Url;

/** @var array<string, string> $errors */
?>
<p class="eyebrow">Acesso</p>
<h2>Entrar</h2>
<p class="lead">Use a conta que concentra os seus monitoramentos.</p>
<form method="post" action="<?= e(Url::to('/login')) ?>" novalidate>
    <?= Csrf::field() ?>
    <div class="mb-3">
        <label class="form-label" for="email">E-mail</label>
        <input class="form-control<?= invalid_class($errors, 'email') ?>" type="email" id="email" name="email" value="<?= e(Session::old('email')) ?>" maxlength="190" autocomplete="username" required>
        <?= field_error($errors, 'email') ?>
    </div>
    <div class="mb-4">
        <label class="form-label" for="senha">Senha</label>
        <div class="password-field">
            <input class="form-control<?= invalid_class($errors, 'senha') ?>" type="password" id="senha" name="senha" maxlength="72" autocomplete="current-password" required>
            <button class="password-toggle" type="button" data-toggle-password="senha" aria-label="Mostrar senha">
                <i class="bi bi-eye"></i>
            </button>
        </div>
        <?= field_error($errors, 'senha') ?>
    </div>
    <button class="btn btn-gold w-100" type="submit">Entrar</button>
</form>
<p class="auth-switch">Ainda não tem conta? <a href="<?= e(Url::to('/cadastro')) ?>">Criar conta</a></p>
