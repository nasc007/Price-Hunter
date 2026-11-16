<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Integrations\MarketplaceDriver;
use App\Integrations\SearchEngine;

$failures = [];

function fase3Check(bool $condition, string $message): void
{
    global $failures;
    if ($condition) {
        echo "ok   {$message}\n";
        return;
    }

    $failures[] = $message;
    echo "FALHA {$message}\n";
}

final class Fase3FixtureDriver implements MarketplaceDriver
{
    public bool $called = false;

    /** @param list<array<string, mixed>> $offers */
    public function __construct(
        private readonly array $offers,
        private readonly bool $active = true,
        private readonly string $marketplaceCode = 'fixture',
        private readonly ?string $failure = null
    ) {
    }

    public function codigo(): string
    {
        return $this->marketplaceCode;
    }

    public function nome(): string
    {
        return 'Fonte de teste';
    }

    public function disponivel(): bool
    {
        return $this->active;
    }

    public function buscar(array $produto): array
    {
        $this->called = true;
        if ($this->failure !== null) {
            throw new RuntimeException($this->failure);
        }

        return $this->offers;
    }
}

$product = [
    'nome' => 'Mini GT Porsche 911 GT3 R',
    'marca' => 'Mini GT',
    'modelo' => '911 GT3 R',
    'sku' => '',
    'ean' => '7891234567895',
];
$candidate = [
    'id_externo' => 'TEST-001',
    'titulo' => 'Mini GT Porsche 911 GT3 R 1:64',
    'marca' => 'Mini GT',
    'modelo' => '911 GT3 R',
    'ean' => '7891234567895',
    'url' => 'https://shop.example.test/p/TEST-001',
    'preco' => '129.90',
    'frete_status' => 'gratis',
    'desconto' => '10.00',
];

$emptyReport = (new SearchEngine([]))->search($product);
fase3Check(
    $emptyReport['status'] === 'sem_fontes' && $emptyReport['resultados'] === [],
    'sem drivers ativos o motor não inventa ofertas'
);

$driver = new Fase3FixtureDriver([$candidate]);
$report = (new SearchEngine([$driver]))->search($product);
fase3Check(
    $driver->called
    && $report['status'] === 'concluida'
    && count($report['resultados']) === 1
    && $report['resultados'][0]['preco_total'] === '119.90'
    && $report['resultados'][0]['correspondencia_score'] === 100
    && $report['resultados'][0]['atributos_match']['metodo'] === 'ean',
    'motor normaliza oferta, calcula total com frete grátis e prioriza EAN exato'
);

$textOnlyProduct = $product;
$textOnlyProduct['ean'] = '';
$candidateWithoutEan = $candidate;
$candidateWithoutEan['ean'] = '';
$textReport = (new SearchEngine([new Fase3FixtureDriver([$candidateWithoutEan])]))->search($textOnlyProduct);
fase3Check(
    $textReport['status'] === 'concluida'
    && $textReport['resultados'][0]['correspondencia_score'] >= 70
    && $textReport['resultados'][0]['atributos_match']['metodo'] === 'texto',
    'motor pode comparar nome, marca e modelo sem EAN/GTIN'
);

$mismatch = $candidate;
$mismatch['ean'] = '7891234567888';
$mismatchReport = (new SearchEngine([new Fase3FixtureDriver([$mismatch])]))->search($product);
fase3Check(
    $mismatchReport['resultados'] === []
    && $mismatchReport['fontes'][0]['rejeitadas'] === 1,
    'motor rejeita oferta com EAN divergente'
);

$unavailable = new Fase3FixtureDriver([$candidate], false);
$unavailableReport = (new SearchEngine([$unavailable]))->search($product);
fase3Check(
    !$unavailable->called
    && $unavailableReport['status'] === 'sem_fontes',
    'motor ignora driver indisponível sem consultar a fonte'
);

$invalid = $candidate;
$invalid['url'] = 'http://shop.example.test/p/TEST-001';
$invalidReport = (new SearchEngine([new Fase3FixtureDriver([$invalid])]))->search($product);
fase3Check(
    $invalidReport['status'] === 'falha'
    && $invalidReport['resultados'] === []
    && count($invalidReport['erros']) === 1,
    'motor retorna erro explícito e não aceita URL sem HTTPS'
);

$failedReport = (new SearchEngine([
    new Fase3FixtureDriver([], true, 'fixture', 'Falha controlada da fonte de teste.'),
]))->search($product);
fase3Check(
    $failedReport['status'] === 'falha'
    && $failedReport['erros'][0]['mensagem'] === 'Falha controlada da fonte de teste.',
    'motor expõe falhas do driver sem convertê-las em busca vazia'
);

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " verificação(ões) falharam.\n");
    exit(1);
}

echo "Fase 3: arquitetura verificada.\n";
