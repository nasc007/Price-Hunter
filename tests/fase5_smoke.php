<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Integrations\GtinValidator;
use App\Integrations\OfferNormalizer;
use App\Integrations\PriceComparator;
use App\Integrations\ProductOfferMatcher;

$failures = [];

function fase5Check(bool $condition, string $message): void
{
    global $failures;
    if ($condition) {
        echo "ok   {$message}\n";
        return;
    }

    $failures[] = $message;
    echo "FALHA {$message}\n";
}

fase5Check(
    GtinValidator::isValid('7891234567895')
    && GtinValidator::isValid('12345670')
    && GtinValidator::isValid('036000291452')
    && GtinValidator::isValid('07891234567895')
    && GtinValidator::isValid('8891234567894')
    && !GtinValidator::isValid('7891234567894')
    && !GtinValidator::isValid('123'),
    'validador aceita GTIN-8/12/13/14 com dígito verificador correto'
);

$product = [
    'nome' => 'Produto de teste Mini GT',
    'marca' => 'Mini GT',
    'modelo' => 'Porsche 911',
    'ean' => '7891234567895',
];
$candidate = [
    'id' => 1,
    'titulo' => 'Mini GT Porsche 911',
    'loja' => 'Loja A',
    'condicao' => 'novo',
    'preco' => '100.00',
    'frete' => '10.00',
    'frete_status' => 'confirmado',
    'desconto' => '5.00',
    'preco_total' => '105.00',
    'disponibilidade' => 'disponivel',
    'moeda' => 'BRL',
    'correspondencia_score' => '100.00',
    'atributos_match' => '{"metodo":"ean"}',
    'ultima_vista_em' => '2026-01-01 10:00:00',
];
$lowerTotal = $candidate;
$lowerTotal['id'] = 2;
$lowerTotal['loja'] = 'Loja B';
$lowerTotal['preco'] = '100.00';
$lowerTotal['frete'] = '0.00';
$lowerTotal['frete_status'] = 'gratis';
$lowerTotal['desconto'] = '2.00';
$lowerTotal['preco_total'] = '98.00';
$unknownFreight = $candidate;
$unknownFreight['id'] = 3;
$unknownFreight['loja'] = 'Loja C';
$unknownFreight['preco'] = '20.00';
$unknownFreight['frete'] = null;
$unknownFreight['frete_status'] = 'desconhecido';
$unknownFreight['desconto'] = '0.00';
$unknownFreight['preco_total'] = null;
$badStoredTotal = $candidate;
$badStoredTotal['id'] = 4;
$badStoredTotal['loja'] = 'Loja D';
$badStoredTotal['preco_total'] = '1.00';
$unmatched = $candidate;
$unmatched['id'] = 5;
$unmatched['loja'] = 'Loja E';
$unmatched['preco'] = '50.00';
$unmatched['desconto'] = '0.00';
$unmatched['preco_total'] = '60.00';
$nearThreshold = $candidate;
$nearThreshold['id'] = 7;
$nearThreshold['loja'] = 'Loja F';
$nearThreshold['correspondencia_score'] = '69.99';
$nearThreshold['atributos_match'] = '{"metodo":"texto"}';
$missingFreeFreight = $lowerTotal;
$missingFreeFreight['id'] = 8;
$missingFreeFreight['loja'] = 'Loja sem valor de frete';
$missingFreeFreight['frete'] = null;
$unmatched['correspondencia_score'] = '40.00';
$unmatched['atributos_match'] = '{"metodo":"texto"}';

$usedOffer = $candidate;
$usedOffer['id'] = 6;
$usedOffer['loja'] = 'Loja usada';
$usedOffer['condicao'] = 'usado';
$usedOffer['preco'] = '10.00';
$usedOffer['frete'] = '0.00';
$usedOffer['frete_status'] = 'gratis';
$usedOffer['desconto'] = '0.00';
$usedOffer['preco_total'] = '10.00';

$comparison = PriceComparator::compare(
    $product,
    [$candidate, $unknownFreight, $badStoredTotal, $unmatched, $lowerTotal, $usedOffer, $nearThreshold, $missingFreeFreight]
);
fase5Check(
    $comparison['menores_por_condicao'] === ['novo' => '98.00', 'usado' => '10.00']
    && $comparison['ofertas_comparaveis'] === 3
    && $comparison['ofertas'][0]['loja'] === 'Loja B'
    && $comparison['ofertas'][0]['menor_preco']
    && $comparison['ofertas'][2]['loja'] === 'Loja usada'
    && $comparison['ofertas'][2]['menor_preco'],
    'comparador calcula menores custos por condição, sem misturar novo e usado'
);

$unknown = array_values(array_filter(
    $comparison['ofertas'],
    static fn (array $offer): bool => $offer['loja'] === 'Loja C'
))[0];
$incorrect = array_values(array_filter(
    $comparison['ofertas'],
    static fn (array $offer): bool => $offer['loja'] === 'Loja D'
))[0];
$notMatched = array_values(array_filter(
    $comparison['ofertas'],
    static fn (array $offer): bool => $offer['loja'] === 'Loja E'
))[0];
$nearThresholdResult = array_values(array_filter(
    $comparison['ofertas'],
    static fn (array $offer): bool => $offer['loja'] === 'Loja F'
))[0];
$missingFreightResult = array_values(array_filter(
    $comparison['ofertas'],
    static fn (array $offer): bool => $offer['loja'] === 'Loja sem valor de frete'
))[0];
fase5Check(
    !$unknown['comparavel']
    && $unknown['preco_total_confirmado'] === null
    && !$incorrect['comparavel']
    && str_contains($incorrect['validacao_preco'], 'divergente')
    && !$notMatched['comparavel']
    && $notMatched['preco_total_confirmado'] === '60.00',
    'frete desconhecido, total inconsistente e produto não validado não vencem o ranking'
);
fase5Check(
    !$nearThresholdResult['identificacao_validada']
    && !$nearThresholdResult['comparavel']
    && !$missingFreightResult['comparavel']
    && $missingFreightResult['preco_total_confirmado'] === null,
    'comparador aplica limite exato de correspondência e exige valor zero explícito para frete grátis'
);

$invalidIdentifierProduct = $product;
$invalidIdentifierProduct['ean'] = '12345678';
$identifierOnlyMatch = $candidate;
$identifierOnlyMatch['atributos_match'] = '{"metodo":"ean"}';
$invalidIdentifierResult = PriceComparator::compare($invalidIdentifierProduct, [$identifierOnlyMatch]);
fase5Check(
    $invalidIdentifierResult['gtin_status'] === 'invalido'
    && !$invalidIdentifierResult['ofertas'][0]['identificacao_validada']
    && !$invalidIdentifierResult['ofertas'][0]['comparavel'],
    'GTIN inválido no cadastro invalida vínculo de oferta baseado nesse código'
);

$matcherResult = ProductOfferMatcher::evaluate(
    ['nome' => 'Produto de teste Mini GT', 'marca' => 'Mini GT', 'modelo' => 'Porsche 911', 'ean' => '12345678'],
    ['titulo' => 'Produto de teste Mini GT Porsche 911', 'marca' => 'Mini GT', 'modelo' => 'Porsche 911', 'ean' => '12345678']
);
fase5Check(
    $matcherResult['atributos']['metodo'] === 'texto' && $matcherResult['score'] >= 70,
    'GTIN inválido não é usado para correspondência exata'
);

$invalidSourceOffer = [
    'titulo' => 'Produto inválido',
    'url' => 'https://loja.example.test/produto',
    'preco' => '10.00',
    'ean' => '7891234567894',
];
$sourceRejected = false;
try {
    OfferNormalizer::normalize($invalidSourceOffer);
} catch (InvalidArgumentException $exception) {
    $sourceRejected = str_contains($exception->getMessage(), 'EAN/GTIN');
}
fase5Check($sourceRejected, 'normalizador rejeita GTIN inválido retornado por uma fonte');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " verificação(ões) falharam.\n");
    exit(1);
}

echo "Fase 5: comparação e validação verificadas.\n";
