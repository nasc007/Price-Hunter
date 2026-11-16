<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

Env::load(dirname(__DIR__) . '/.env');

$base = 'http://127.0.0.1:8090';
$suffix = bin2hex(random_bytes(5));
$ownerEmail = 'fase2-' . $suffix . '@pricehunter.test';
$otherEmail = 'fase2-outro-' . $suffix . '@pricehunter.test';
$failures = [];

function fase2Check(bool $condition, string $message): void
{
    global $failures;
    if ($condition) {
        echo "ok   {$message}\n";
        return;
    }

    $failures[] = $message;
    echo "FALHA {$message}\n";
}

final class Fase2Client
{
    /** @var array<string, string> */
    private array $cookies = [];

    /** @return array{status: int, body: string, location: string} */
    public function request(string $method, string $url, array $data = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 20,
        ]);
        if ($this->cookies !== []) {
            $pairs = [];
            foreach ($this->cookies as $name => $value) {
                $pairs[] = $name . '=' . $value;
            }
            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $pairs));
        }
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException($error);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $header = substr($raw, 0, $headerSize);
        if (preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;]*)/mi', $header, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->cookies[$match[1]] = $match[2];
            }
        }
        $location = preg_match('/^Location:\s*(\S+)/mi', $header, $match) === 1 ? $match[1] : '';

        return ['status' => $status, 'body' => substr($raw, $headerSize), 'location' => $location];
    }

    /** @param array<string, mixed> $data
     *  @return array{status: int, body: string, location: string} */
    public function follow(string $method, string $url, array $data = []): array
    {
        $response = $this->request($method, $url, $data);
        for ($i = 0; $i < 4 && $response['status'] >= 300 && $response['status'] < 400; $i++) {
            if ($response['location'] === '') {
                break;
            }
            $target = str_starts_with($response['location'], 'http')
                ? $response['location']
                : $GLOBALS['base'] . $response['location'];
            $response = $this->request('GET', $target);
        }

        return $response;
    }
}

function fase2Csrf(string $html): string
{
    if (preg_match('/name="_csrf" value="([^"]+)"/', $html, $match) !== 1) {
        throw new RuntimeException('Token CSRF não encontrado.');
    }

    return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
}

$pdo = Database::connection();
$owner = new Fase2Client();
$other = new Fase2Client();

try {
    $register = $owner->request('GET', $base . '/cadastro');
    $home = $owner->follow('POST', $base . '/cadastro', [
        '_csrf' => fase2Csrf($register['body']),
        'nome' => 'Usuário Fase 2',
        'email' => $ownerEmail,
        'senha' => 'senha-fase-2',
        'senha_confirmacao' => 'senha-fase-2',
    ]);
    fase2Check($home['status'] === 200 && str_contains($home['body'], 'Produtos'), 'usuário acessa o catálogo protegido');

    $newPage = $owner->request('GET', $base . '/produtos/novo');
    fase2Check(
        $newPage['status'] === 200
        && str_contains($newPage['body'], 'Adicionar outro produto')
        && str_contains($newPage['body'], 'Link original do produto')
        && str_contains($newPage['body'], 'Código interno do fabricante')
        && str_contains($newPage['body'], 'Número do código de barras')
        && !str_contains($newPage['body'], 'Criar cadastro a partir de um link'),
        'formulário manual explica SKU e EAN/GTIN e mantém o link de referência opcional'
    );

    $newPage = $owner->request('GET', $base . '/produtos/novo');
    $invalid = $owner->follow('POST', $base . '/produtos', [
        '_csrf' => fase2Csrf($newPage['body']),
        'produtos' => [
            ['nome' => 'Item inválido', 'ean' => '123', 'preco_desejado' => '12.345'],
        ],
    ]);
    $ownerId = (int) $pdo->query("SELECT id FROM usuarios WHERE email = " . $pdo->quote($ownerEmail))->fetchColumn();
    $count = $pdo->prepare('SELECT COUNT(*) FROM produtos WHERE usuario_id = :usuario_id');
    $count->execute(['usuario_id' => $ownerId]);
    fase2Check(
        str_contains($invalid['body'], 'EAN/GTIN')
        && str_contains($invalid['body'], '2 casas decimais')
        && (int) $count->fetchColumn() === 0,
        'dados inválidos são rejeitados sem salvar parte do lote'
    );

    $newPage = $owner->request('GET', $base . '/produtos/novo');
    $listing = $owner->follow('POST', $base . '/produtos', [
        '_csrf' => fase2Csrf($newPage['body']),
        'produtos' => [
            [
                'nome' => 'Mini GT Ford Mustang',
                'marca' => 'Mini GT',
                'modelo' => 'Ford Mustang 1:64',
                'categoria' => 'Miniaturas',
                'descricao' => 'Miniatura para monitorar',
                'sku' => 'MGT-MUSTANG-01',
                'ean' => '12345678',
                'url_referencia' => 'https://www.mercadolivre.com.br/mini-gt-ford-mustang/p/MLB11111111?pdp_filters=item_id%3AMLB12345678',
                'palavras_chave' => 'Ford, Mustang, 1:64',
                'preco_desejado' => '80.00',
                'valor_maximo' => '100.00',
                'status' => 'ativo',
                'frequencia_horas' => '6',
            ],
            [
                'nome' => 'Furadeira de teste',
                'marca' => 'Ferramentas',
                'categoria' => 'Ferramentas',
                'status' => 'pausado',
                'frequencia_horas' => '24',
            ],
        ],
    ]);
    $count->execute(['usuario_id' => $ownerId]);
    $productCount = (int) $count->fetchColumn();
    fase2Check(
        $productCount === 2
        && str_contains($listing['body'], 'Mini GT Ford Mustang')
        && str_contains($listing['body'], 'Furadeira de teste')
        && str_contains($listing['body'], 'Abrir link do produto'),
        'lote válido salva e lista os dois produtos'
    );

    $find = $pdo->prepare(
        'SELECT id, status, frequencia_horas, preco_desejado, url_referencia
         FROM produtos WHERE usuario_id = :usuario_id AND sku = :sku'
    );
    $find->execute(['usuario_id' => $ownerId, 'sku' => 'MGT-MUSTANG-01']);
    $product = $find->fetch();
    $productId = is_array($product) ? (int) $product['id'] : 0;
    $monitor = $pdo->prepare(
        'SELECT ativo, frequencia_horas, proxima_execucao_em
         FROM monitoramentos WHERE produto_id = :produto_id'
    );
    $monitor->execute(['produto_id' => $productId]);
    $monitorRow = $monitor->fetch();
    fase2Check(
        $productId > 0
        && $product['status'] === 'ativo'
        && (int) $product['frequencia_horas'] === 6
        && $product['preco_desejado'] === '80.00'
        && $product['url_referencia'] === 'https://www.mercadolivre.com.br/mini-gt-ford-mustang/p/MLB11111111?pdp_filters=item_id%3AMLB12345678'
        && is_array($monitorRow)
        && (int) $monitorRow['ativo'] === 1
        && (int) $monitorRow['frequencia_horas'] === 6,
        'produto e monitoramento persistem os campos e a frequência'
    );

    $edit = $owner->request('GET', $base . '/produtos/' . $productId . '/editar');
    $updated = $owner->follow('POST', $base . '/produtos/' . $productId, [
        '_csrf' => fase2Csrf($edit['body']),
        'produto' => [
            'nome' => 'Mini GT Mustang atualizado',
            'marca' => 'Mini GT',
            'modelo' => 'Ford Mustang 1:64',
            'categoria' => 'Miniaturas',
            'sku' => 'MGT-MUSTANG-01',
            'ean' => '12345678',
            'url_referencia' => 'https://www.mercadolivre.com.br/mini-gt-ford-mustang/p/MLB11111111?pdp_filters=item_id%3AMLB12345678',
            'preco_desejado' => '75.50',
            'valor_maximo' => '100.00',
            'status' => 'ativo',
            'frequencia_horas' => '12',
        ],
    ]);
    $find->execute(['usuario_id' => $ownerId, 'sku' => 'MGT-MUSTANG-01']);
    $product = $find->fetch();
    fase2Check(
        str_contains($updated['body'], 'Mini GT Mustang atualizado')
        && is_array($product)
        && $product['preco_desejado'] === '75.50'
        && $product['url_referencia'] === 'https://www.mercadolivre.com.br/mini-gt-ford-mustang/p/MLB11111111?pdp_filters=item_id%3AMLB12345678'
        && (int) $product['frequencia_horas'] === 12,
        'edição atualiza dados e frequência'
    );

    $statusPage = $owner->request('GET', $base . '/produtos');
    $paused = $owner->follow('POST', $base . '/produtos/' . $productId . '/status', [
        '_csrf' => fase2Csrf($statusPage['body']),
        'status' => 'pausado',
    ]);
    $monitor->execute(['produto_id' => $productId]);
    $monitorRow = $monitor->fetch();
    fase2Check(
        str_contains($paused['body'], 'Monitoramento pausado')
        && is_array($monitorRow)
        && (int) $monitorRow['ativo'] === 0
        && $monitorRow['proxima_execucao_em'] === null,
        'pausar produto desativa o monitoramento'
    );

    $otherRegister = $other->request('GET', $base . '/cadastro');
    $other->follow('POST', $base . '/cadastro', [
        '_csrf' => fase2Csrf($otherRegister['body']),
        'nome' => 'Outro usuário',
        'email' => $otherEmail,
        'senha' => 'senha-fase-2',
        'senha_confirmacao' => 'senha-fase-2',
    ]);
    $otherList = $other->request('GET', $base . '/produtos');
    $forbiddenEdit = $other->request('GET', $base . '/produtos/' . $productId . '/editar');
    fase2Check(
        !str_contains($otherList['body'], 'Mini GT Mustang atualizado')
        && $forbiddenEdit['status'] === 404,
        'usuário não lista nem acessa produto de outra conta'
    );

    $deletePage = $owner->request('GET', $base . '/produtos');
    $deleted = $owner->follow('POST', $base . '/produtos/' . $productId . '/excluir', [
        '_csrf' => fase2Csrf($deletePage['body']),
    ]);
    $checkDeleted = $pdo->prepare('SELECT COUNT(*) FROM produtos WHERE id = :id AND usuario_id = :usuario_id');
    $checkDeleted->execute(['id' => $productId, 'usuario_id' => $ownerId]);
    $monitor->execute(['produto_id' => $productId]);
    fase2Check(
        str_contains($deleted['body'], 'Produto excluído')
        && (int) $checkDeleted->fetchColumn() === 0
        && $monitor->fetch() === false,
        'exclusão remove produto e monitoramento associado'
    );
} finally {
    $cleanup = $pdo->prepare('DELETE FROM usuarios WHERE email IN (:owner, :other)');
    $cleanup->execute(['owner' => $ownerEmail, 'other' => $otherEmail]);
}

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " verificação(ões) falharam.\n");
    exit(1);
}

echo "Fase 2 verificada.\n";
