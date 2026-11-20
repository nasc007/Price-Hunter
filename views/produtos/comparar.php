<?php

declare(strict_types=1);

use App\Core\Url;
use App\Helpers\Format;

/** @var array<string, mixed> $produto */
/** @var array{gtin_status: string, menores_por_condicao: array<string, string>, ofertas_comparaveis: int, ofertas: list<array<string, mixed>>} $comparacao */

$gtinMessages = [
    'ausente' => 'Sem EAN/GTIN informado. As ofertas dependem da correspondência por SKU ou descrição.',
    'valido' => 'EAN/GTIN válido; usado como identificador quando disponível na oferta.',
    'invalido' => 'EAN/GTIN inválido. Corrija o dígito verificador para validar ofertas por código.',
];
$freightLabels = [
    'confirmado' => 'Confirmado',
    'gratis' => 'Grátis',
    'indisponivel' => 'Indisponível',
    'desconhecido' => 'Não informado',
];
$availabilityLabels = [
    'disponivel' => 'Disponível',
    'indisponivel' => 'Indisponível',
    'sob_consulta' => 'Sob consulta',
];
$conditionLabels = [
    'novo' => 'Novo',
    'usado' => 'Usado',
    'recondicionado' => 'Recondicionado',
    'desconhecido' => 'Não informada',
];
$money = static function (mixed $value, string $currency): string {
    if ($value === null || $value === '') {
        return '—';
    }
    if ($currency === 'BRL') {
        return Format::money($value);
    }

    return $currency . ' ' . number_format((float) $value, 2, ',', '.');
};
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Comparador de ofertas</p>
        <h1><?= e((string) $produto['nome']) ?></h1>
        <p class="lead">
            Compare apenas produtos validados e custos com preço, frete e desconto confirmados.
            Preço desejado: <?= e(Format::money($produto['preco_desejado'] ?? null)) ?>;
            valor máximo: <?= e(Format::money($produto['valor_maximo'] ?? null)) ?>.
        </p>
    </div>
    <a class="btn btn-line" href="<?= e(Url::to('/produtos')) ?>">Voltar aos produtos</a>
</section>

<section class="panel comparison-summary">
    <div>
        <span class="muted-cell">Identificador do produto</span>
        <strong><?= e($gtinMessages[$comparacao['gtin_status']]) ?></strong>
    </div>
    <div>
        <span class="muted-cell">Menor custo por condição</span>
        <?php if ($comparacao['menores_por_condicao'] === []): ?>
            <strong class="comparison-lowest">—</strong>
        <?php else: ?>
            <?php foreach ($comparacao['menores_por_condicao'] as $condition => $total): ?>
                <strong class="comparison-lowest">
                    <?= e($conditionLabels[$condition] ?? $condition) ?>:
                    <?= e(Format::money($total)) ?>
                </strong>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div>
        <span class="muted-cell">Ofertas comparáveis</span>
        <strong><?= (int) $comparacao['ofertas_comparaveis'] ?></strong>
    </div>
</section>

<?php if ($comparacao['gtin_status'] === 'invalido'): ?>
    <p class="comparison-warning" role="alert">
        <i class="bi bi-exclamation-triangle"></i>
        O EAN/GTIN salvo não passou na validação. Edite o produto antes de confiar em correspondências por código.
    </p>
<?php endif; ?>

<section class="panel">
    <header class="panel-head">
        <h2>Ofertas registradas</h2>
        <p>
            Frete desconhecido nunca é tratado como grátis. Ofertas sem correspondência confirmada,
            disponibilidade ou preço total válido não entram no ranking.
        </p>
    </header>

    <?php if ($comparacao['ofertas'] === []): ?>
        <div class="empty">
            <i class="bi bi-search"></i>
            <p>Nenhuma oferta foi registrada para este produto.</p>
            <p>As integrações ainda não gravaram resultados; não há preços a comparar.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table comparison-table">
                <thead>
                    <tr>
                        <th>Loja e produto</th>
                        <th>Validação</th>
                        <th>Condição</th>
                        <th>Preço</th>
                        <th>Frete</th>
                        <th>Desconto</th>
                        <th>Total</th>
                        <th>Disponibilidade</th>
                        <th>Atualizado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comparacao['ofertas'] as $oferta): ?>
                        <?php
                        $currency = strtoupper((string) ($oferta['moeda'] ?? 'BRL'));
                        $url = (string) ($oferta['url'] ?? '');
                        $safeUrl = preg_match('#^https://#i', $url) === 1;
                        $freightStatus = (string) ($oferta['frete_status'] ?? 'desconhecido');
                        $freight = $freightStatus === 'gratis'
                            ? 'Grátis'
                            : ($freightStatus === 'confirmado'
                                ? $money($oferta['frete'] ?? null, $currency)
                                : ($freightLabels[$freightStatus] ?? 'Não informado'));
                        ?>
                        <tr>
                            <td>
                                <strong><?= e((string) ($oferta['loja'] ?: $oferta['marketplace_nome'])) ?></strong>
                                <span class="comparison-subtitle"><?= e((string) $oferta['titulo']) ?></span>
                                <?php if (!empty($oferta['vendedor'])): ?>
                                    <span class="comparison-subtitle">Vendedor: <?= e((string) $oferta['vendedor']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($oferta['identificacao_validada']): ?>
                                    <span class="pill pill-ready">Produto conferido · <?= (int) $oferta['correspondencia_score'] ?>%</span>
                                <?php else: ?>
                                    <span class="pill"><?= e((string) $oferta['identificacao_mensagem']) ?></span>
                                <?php endif; ?>
                                <span class="comparison-subtitle"><?= e((string) $oferta['validacao_comparacao']) ?></span>
                            </td>
                            <td><?= e($conditionLabels[(string) ($oferta['condicao'] ?? '')] ?? 'Não informada') ?></td>
                            <td><?= e($money($oferta['preco'] ?? null, $currency)) ?></td>
                            <td><?= e($freight) ?></td>
                            <td><?= e($money($oferta['desconto'] ?? null, $currency)) ?></td>
                            <td class="total">
                                <?php if ($oferta['menor_preco']): ?>
                                    <span class="pill pill-ready">Menor custo</span>
                                <?php endif; ?>
                                <?= e($money($oferta['preco_total_confirmado'], $currency)) ?>
                            </td>
                            <td><?= e($availabilityLabels[(string) ($oferta['disponibilidade'] ?? '')] ?? 'Não confirmado') ?></td>
                            <td><?= e(Format::dateTime((string) ($oferta['ultima_vista_em'] ?? ''), '—')) ?></td>
                            <td>
                                <?php if ($safeUrl): ?>
                                    <a class="text-link" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer">Ver oferta</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
