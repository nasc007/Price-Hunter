<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Session;
use App\Core\Url;
use App\Helpers\Format;

/** @var array<string, mixed>|null $authUser */
/** @var array<string, string> $errors */

$nome = Session::old('nome', (string) ($authUser['nome'] ?? ''));
$email = Session::old('email', (string) ($authUser['email'] ?? ''));
$telefone = Session::old('telefone', (string) ($authUser['telefone'] ?? ''));
$whatsapp = Session::old('whatsapp', (string) ($authUser['whatsapp'] ?? ''));
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Conta</p>
        <h1>Perfil</h1>
        <p class="lead">Estes dados identificam você e preparam os canais de alerta das próximas fases.</p>
    </div>
</section>

<div class="split">
    <article class="panel">
        <header class="panel-head">
            <h2>Dados pessoais</h2>
            <p>Conta criada em <?= e(Format::dateTime(isset($authUser['created_at']) ? (string) $authUser['created_at'] : null, '—')) ?>.</p>
        </header>
        <form method="post" action="<?= e(Url::to('/perfil')) ?>" novalidate>
            <?= Csrf::field() ?>
            <div class="mb-3">
                <label class="form-label" for="nome">Nome</label>
                <input class="form-control<?= invalid_class($errors, 'nome') ?>" type="text" id="nome" name="nome" value="<?= e($nome) ?>" maxlength="120" required>
                <?= field_error($errors, 'nome') ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">E-mail</label>
                <input class="form-control<?= invalid_class($errors, 'email') ?>" type="email" id="email" name="email" value="<?= e($email) ?>" maxlength="190" required>
                <?= field_error($errors, 'email') ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="telefone">Telefone</label>
                <input class="form-control<?= invalid_class($errors, 'telefone') ?>" type="text" id="telefone" name="telefone" value="<?= e($telefone) ?>" maxlength="20" inputmode="tel">
                <?= field_error($errors, 'telefone') ?>
            </div>
            <div class="mb-4">
                <label class="form-label" for="whatsapp">WhatsApp</label>
                <input class="form-control<?= invalid_class($errors, 'whatsapp') ?>" type="text" id="whatsapp" name="whatsapp" value="<?= e($whatsapp) ?>" maxlength="20" inputmode="tel">
                <div class="form-text">O envio por WhatsApp depende de um provedor autorizado e entra na Fase 8.</div>
                <?= field_error($errors, 'whatsapp') ?>
            </div>
            <button class="btn btn-gold" type="submit">Salvar perfil</button>
        </form>
    </article>

    <article class="panel">
        <header class="panel-head">
            <h2>Senha</h2>
            <p>Último acesso: <?= e(Format::dateTime(isset($authUser['ultimo_login_em']) ? (string) $authUser['ultimo_login_em'] : null, 'neste acesso')) ?>.</p>
        </header>
        <form method="post" action="<?= e(Url::to('/perfil/senha')) ?>" novalidate>
            <?= Csrf::field() ?>
            <div class="mb-3">
                <label class="form-label" for="senha_atual">Senha atual</label>
                <input class="form-control<?= invalid_class($errors, 'senha_atual') ?>" type="password" id="senha_atual" name="senha_atual" maxlength="72" autocomplete="current-password" required>
                <?= field_error($errors, 'senha_atual') ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="senha">Nova senha</label>
                <input class="form-control<?= invalid_class($errors, 'senha') ?>" type="password" id="senha" name="senha" maxlength="72" autocomplete="new-password" required>
                <?= field_error($errors, 'senha') ?>
            </div>
            <div class="mb-4">
                <label class="form-label" for="senha_confirmacao">Confirmar nova senha</label>
                <input class="form-control" type="password" id="senha_confirmacao" name="senha_confirmacao" maxlength="72" autocomplete="new-password" required>
            </div>
            <button class="btn btn-line" type="submit">Alterar senha</button>
        </form>
    </article>
</div>
