<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Url;
use App\Helpers\Format;

/** @var list<array<string, mixed>> $produtos */
/** @var array{status: string, q: string} $filtros */

$statusLabels = [
    'ativo' => 'Ativo',
    'pausado' => 'Pausado',
    'arquivado' => 'Arquivado',
];
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Catálogo pessoal</p>
        <h1>Produtos</h1>
        <p class="lead">Gerencie os produtos que deseja acompanhar. O cadastro não executa pesquisas.</p>
    </div>
    <a class="btn btn-gold" href="<?= e(Url::to('/produtos/novo')) ?>">
        <i class="bi bi-plus-lg"></i> Cadastrar produto
    </a>
</section>

<section class="panel">
    <form method="get" action="<?= e(Url::to('/produtos')) ?>" class="product-filters">
        <div>
            <label class="form-label" for="busca">Buscar</label>
            <input class="form-control" type="search" id="busca" name="q" value="<?= e($filtros['q']) ?>" placeholder="Nome, marca, modelo, categoria, SKU ou EAN">
        </div>
        <div>
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">Todos</option>
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= $filtros['status'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-line" type="submit">Filtrar</button>
        <?php if ($filtros['q'] !== '' || $filtros['status'] !== ''): ?>
            <a class="text-link filter-clear" href="<?= e(Url::to('/produtos')) ?>">Limpar filtros</a>
        <?php endif; ?>
    </form>
</section>

<section class="panel">
    <?php if ($produtos === []): ?>
        <div class="empty">
            <i class="bi bi-box-seam"></i>
            <?php if ($filtros['q'] !== '' || $filtros['status'] !== ''): ?>
                <p>Nenhum produto corresponde aos filtros.</p>
            <?php else: ?>
                <p>Seu catálogo ainda está vazio. Cadastre um ou mais produtos para começar.</p>
                <a class="btn btn-gold" href="<?= e(Url::to('/produtos/novo')) ?>">Cadastrar produtos</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Preço desejado</th>
                        <th>Frequência</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $produto): ?>
                        <?php
                        $image = (string) ($produto['imagem'] ?? '');
                        $safeImage = preg_match('#^https://#i', $image) === 1;
                        $reference = (string) ($produto['url_referencia'] ?? '');
                        $safeReference = preg_match('#^https://#i', $reference) === 1;
                        $productId = (int) $produto['id'];
                        $productStatus = (string) $produto['status'];
                        ?>
                        <tr>
                            <td>
                                <div class="product-name-cell">
                                    <?php if ($safeImage): ?>
                                        <img class="product-thumb" src="<?= e($image) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                                    <?php else: ?>
                                        <span class="product-thumb product-thumb-empty"><i class="bi bi-box"></i></span>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?= e((string) $produto['nome']) ?></strong>
                                        <span class="product-subtitle">
                                            <?= e(implode(' · ', array_filter([
                                                (string) ($produto['marca'] ?? ''),
                                                (string) ($produto['modelo'] ?? ''),
                                                (string) ($produto['sku'] ?? ''),
                                            ]))) ?>
                                        </span>
                                        <?php if ($safeReference): ?>
                                            <a class="product-reference" href="<?= e($reference) ?>" target="_blank" rel="noopener noreferrer">Abrir link do produto</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= e((string) ($produto['categoria'] ?? '—')) ?></td>
                            <td><?= e(Format::money($produto['preco_desejado'] ?? null)) ?></td>
                            <td>A cada <?= (int) $produto['frequencia_horas'] ?>h</td>
                            <td>
                                <span class="pill<?= $productStatus === 'ativo' ? ' pill-ready' : '' ?>">
                                    <?= e($statusLabels[$productStatus] ?? '—') ?>
                                </span>
                            </td>
                            <td>
                                <div class="product-actions">
                                    <a class="text-link" href="<?= e(Url::to('/produtos/' . $productId . '/comparar')) ?>">Comparar preços</a>
                                    <a class="text-link" href="<?= e(Url::to('/produtos/' . $productId . '/editar')) ?>">Editar</a>
                                    <?php if ($productStatus !== 'arquivado'): ?>
                                        <form method="post" action="<?= e(Url::to('/produtos/' . $productId . '/status')) ?>">
                                            <?= Csrf::field() ?>
                                            <input type="hidden" name="status" value="<?= $productStatus === 'ativo' ? 'pausado' : 'ativo' ?>">
                                            <button class="text-button" type="submit"><?= $productStatus === 'ativo' ? 'Pausar' : 'Retomar' ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="<?= e(Url::to('/produtos/' . $productId . '/excluir')) ?>" data-confirm="Excluir este produto e seus dados associados?">
                                        <?= Csrf::field() ?>
                                        <button class="text-button text-danger" type="submit">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
