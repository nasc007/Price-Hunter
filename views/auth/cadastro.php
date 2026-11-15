<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Session;
use App\Core\Url;

/** @var array<string, string> $errors */
?>
<p class="eyebrow">Nova conta</p>
<h2>Criar conta</h2>
<p class="lead">Os produtos e alertas ficam vinculados somente a você.</p>
<form method="post" action="<?= e(Url::to('/cadastro')) ?>" novalidate>
    <?= Csrf::field() ?>
    <div class="mb-3">
        <label class="form-label" for="nome">Nome</label>
        <input class="form-control<?= invalid_class($errors, 'nome') ?>" type="text" id="nome" name="nome" value="<?= e(Session::old('nome')) ?>" maxlength="120" autocomplete="name" required>
        <?= field_error($errors, 'nome') ?>
    </div>
    <div class="mb-3">
        <label class="form-label" for="email">E-mail</label>
        <input class="form-control<?= invalid_class($errors, 'email') ?>" type="email" id="email" name="email" value="<?= e(Session::old('email')) ?>" maxlength="190" autocomplete="email" required>
        <?= field_error($errors, 'email') ?>
    </div>
    <div class="mb-3">
        <label class="form-label" for="senha">Senha</label>
        <div class="password-field">
            <input class="form-control<?= invalid_class($errors, 'senha') ?>" type="password" id="senha" name="senha" maxlength="72" autocomplete="new-password" required>
            <button class="password-toggle" type="button" data-toggle-password="senha" aria-label="Mostrar senha">
                <i class="bi bi-eye"></i>
            </button>
        </div>
        <div class="form-text">Mínimo de 8 caracteres; máximo de 72 bytes.</div>
        <?= field_error($errors, 'senha') ?>
    </div>
    <div class="mb-4">
        <label class="form-label" for="senha_confirmacao">Confirmar senha</label>
        <input class="form-control" type="password" id="senha_confirmacao" name="senha_confirmacao" maxlength="72" autocomplete="new-password" required>
    </div>
    <button class="btn btn-gold w-100" type="submit">Criar conta</button>
</form>
<p class="auth-switch">Já possui conta? <a href="<?= e(Url::to('/login')) ?>">Entrar</a></p>
