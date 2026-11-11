<?php

declare(strict_types=1);

use App\Helpers\Format;

/** @var array<string, mixed>|null $authUser */
/** @var array{produtos: int, lojas: int, ofertas: int, no_alvo: int, ultima_consulta: ?string} $resumo */
/** @var list<array<string, mixed>> $menores */
/** @var list<array<string, mixed>> $alteracoes */
/** @var list<array<string, mixed>> $fontes */

$partes = preg_split('/\s+/', trim((string) ($authUser['nome'] ?? ''))) ?: [];
$primeiroNome = (string) ($partes[0] ?? '');
$primeiro = $primeiroNome !== '' ? $primeiroNome : 'usuário';
$tipos = [
    'api' => 'API',
    'feed' => 'Feed',
    'autorizado' => 'Fonte autorizada',
];
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Visão geral</p>
        <h1>Olá, <?= e($primeiro) ?></h1>
        <p class="lead">Os números abaixo vêm do banco. Sem consulta real, não há preço, vendedor ou oferta.</p>
    </div>
    <div class="head-meta">
        <span>Última consulta</span>
        <strong><?= e(Format::dateTime($resumo['ultima_consulta'])) ?></strong>
    </div>
</section>

<section class="stat-grid" aria-label="Resumo">
    <article class="stat-card">
        <span class="stat-icon"><i class="bi bi-box-seam"></i></span>
        <span class="stat-label">Produtos monitorados</span>
        <strong data-stat="produtos"><?= (int) $resumo['produtos'] ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-icon"><i class="bi bi-shop"></i></span>
        <span class="stat-label">Lojas consultadas</span>
        <strong data-stat="lojas"><?= (int) $resumo['lojas'] ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-icon"><i class="bi bi-tags"></i></span>
        <span class="stat-label">Ofertas encontradas</span>
        <strong data-stat="ofertas"><?= (int) $resumo['ofertas'] ?></strong>
    </article>
    <article class="stat-card">
        <span class="stat-icon"><i class="bi bi-bullseye"></i></span>
        <span class="stat-label">No preço desejado</span>
        <strong data-stat="alvo"><?= (int) $resumo['no_alvo'] ?></strong>
    </article>
</section>

<section class="split">
    <article class="panel">
        <header class="panel-head">
            <h2>Menor custo confirmado</h2>
            <p>Somente ofertas com frete conhecido entram nesta ordem.</p>
        </header>
        <?php if ($menores === []): ?>
            <div class="empty">
                <i class="bi bi-search"></i>
                <p>Nenhum preço total confirmado. Oferta com frete desconhecido não aparece como menor custo.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Loja</th>
                            <th>Preço</th>
                            <th>Frete</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($menores as $oferta): ?>
                            <?php
                            $url = (string) ($oferta['url'] ?? '');
                            $urlSegura = preg_match('#^https://#', $url) === 1;
                            ?>
                            <tr>
                                <td><?= e((string) $oferta['produto']) ?></td>
                                <td><?= e((string) ($oferta['loja'] ?? '—')) ?></td>
                                <td><?= e(Format::money($oferta['preco'] ?? null)) ?></td>
                                <td><?= e(Format::money($oferta['frete'] ?? null)) ?></td>
                                <td class="total"><?= e(Format::money($oferta['preco_total'] ?? null)) ?></td>
                                <td>
                                    <?php if ($urlSegura): ?>
                                        <a class="text-link" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer">Ver oferta</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>

    <article class="panel">
        <header class="panel-head">
            <h2>Alterações recentes</h2>
            <p>Histórico gravado a cada consulta concluída.</p>
        </header>
        <?php if ($alteracoes === []): ?>
            <div class="empty">
                <i class="bi bi-clock-history"></i>
                <p>Quando uma consulta real registrar um preço, a alteração aparece aqui.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Quando</th>
                            <th>Produto</th>
                            <th>Loja</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alteracoes as $item): ?>
                            <tr>
                                <td><?= e(Format::dateTime((string) ($item['consultado_em'] ?? ''), '—')) ?></td>
                                <td><?= e((string) $item['produto']) ?></td>
                                <td><?= e((string) ($item['loja'] ?? '—')) ?></td>
                                <td><?= e(Format::money($item['preco_total'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>
</section>

<section class="panel">
    <header class="panel-head">
        <h2>Fontes preparadas</h2>
        <p>Catálogo das integrações. Estar ativa aqui ainda não significa que uma busca foi executada.</p>
    </header>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Marketplace</th>
                    <th>Tipo</th>
                    <th>Situação</th>
                    <th>Observação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fontes as $fonte): ?>
                    <?php
                    $site = (string) ($fonte['site_url'] ?? '');
                    $siteSeguro = preg_match('#^https://#', $site) === 1;
                    ?>
                    <tr>
                        <td>
                            <?php if ($siteSeguro): ?>
                                <a class="text-link" href="<?= e($site) ?>" target="_blank" rel="noopener noreferrer"><?= e((string) $fonte['nome']) ?></a>
                            <?php else: ?>
                                <?= e((string) $fonte['nome']) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= e($tipos[(string) ($fonte['tipo'] ?? '')] ?? (string) $fonte['tipo']) ?></td>
                        <td>
                            <?php if ((int) $fonte['ativo'] === 1): ?>
                                <span class="pill pill-ready">No catálogo</span>
                            <?php else: ?>
                                <span class="pill">Aguardando credencial</span>
                            <?php endif; ?>
                        </td>
                        <td class="muted-cell"><?= e((string) ($fonte['observacao'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
