<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Integrations\AmazonCreatorsApiDriver;
use App\Integrations\AuthorizedJsonFeedDriver;
use App\Integrations\SearchEngine;

$failures = [];

function fase4Check(bool $condition, string $message): void
{
    global $failures;
    if ($condition) {
        echo "ok   {$message}\n";
        return;
    }

    $failures[] = $message;
    echo "FALHA {$message}\n";
}

$amazon = new AmazonCreatorsApiDriver([]);
fase4Check(!$amazon->disponivel(), 'Amazon permanece desativada sem credenciais');

$networkSkipped = false;
try {
    $amazon->buscar(['nome' => 'Produto de teste']);
} catch (RuntimeException $exception) {
    $networkSkipped = str_contains($exception->getMessage(), 'desativada');
}
fase4Check($networkSkipped, 'driver Amazon não tenta acessar a rede sem configuração');

$mapped = AmazonCreatorsApiDriver::mapResponse([
    'itemsResult' => [
        'items' => [[
            'asin' => 'B0ABC12345',
            'detailPageURL' => 'https://www.amazon.com.br/dp/B0ABC12345?tag=teste-20',
            'itemInfo' => [
                'title' => ['displayValue' => 'Miniatura Porsche 911 GT3 R'],
                'byLineInfo' => ['brand' => ['displayValue' => 'Mini GT']],
                'manufactureInfo' => ['model' => ['displayValue' => '911 GT3 R']],
                'externalIds' => ['eans' => ['displayValues' => ['7891234567895']]],
            ],
            'images' => ['primary' => ['medium' => ['url' => 'https://images.example.test/porsche.jpg']]],
            'offersV2' => [
                'listings' => [[
                    'price' => ['money' => ['amount' => '129.90', 'currency' => 'BRL']],
                    'availability' => ['type' => 'IN_STOCK'],
                    'condition' => ['value' => 'New'],
                    'merchantInfo' => ['name' => 'Amazon.com.br'],
                ]],
            ],
        ]],
    ],
], 'teste-20');
$normalized = \App\Integrations\OfferNormalizer::normalize($mapped[0]);
fase4Check(
    count($mapped) === 1
    && $normalized['preco'] === '129.90'
    && $normalized['frete_status'] === 'desconhecido'
    && $normalized['ean'] === '7891234567895'
    && $normalized['atributos']['asin'] === 'B0ABC12345',
    'mapeamento Amazon preserva preço, identificadores e não presume frete'
);

$emptyFeed = new AuthorizedJsonFeedDriver('');
fase4Check(!$emptyFeed->disponivel(), 'feed JSON permanece desativado sem caminho configurado');

$feedPath = tempnam(sys_get_temp_dir(), 'price-hunter-feed-');
if ($feedPath === false) {
    $failures[] = 'criar arquivo temporário de feed para teste';
    echo "FALHA criar arquivo temporário de feed para teste\n";
} else {
    try {
        $feedOffers = [[
            'titulo' => 'Produto anunciado',
            'url' => 'https://loja.example.test/produto',
            'preco' => '129.90',
            'frete_status' => 'desconhecido',
            'disponibilidade' => 'disponivel',
        ]];
        file_put_contents($feedPath, json_encode($feedOffers, JSON_THROW_ON_ERROR));
        $feed = new AuthorizedJsonFeedDriver($feedPath, 'feed_teste', 'Feed de teste');
        $report = (new SearchEngine([$feed]))->search([
            'nome' => 'Produto anunciado',
            'marca' => '',
            'modelo' => '',
            'sku' => '',
            'ean' => '',
        ]);
        fase4Check(
            $report['status'] === 'concluida'
            && count($report['resultados']) === 1
            && $report['resultados'][0]['frete_status'] === 'desconhecido',
            'feed autorizado fornece ofertas válidas ao motor sem inventar custo de frete'
        );

        file_put_contents($feedPath, '{invalid');
        $invalidFeedFailed = false;
        try {
            $feed->buscar([]);
        } catch (RuntimeException $exception) {
            $invalidFeedFailed = str_contains($exception->getMessage(), 'JSON inválido');
        }
        fase4Check($invalidFeedFailed, 'feed JSON inválido gera erro explícito');
    } finally {
        @unlink($feedPath);
    }
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " verificação(ões) falharam.\n");
    exit(1);
}

echo "Fase 4: conectores autorizados verificados.\n";
