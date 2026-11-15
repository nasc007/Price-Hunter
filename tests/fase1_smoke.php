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
$failures = [];

function check(bool $condition, string $message): void
{
    global $failures;
    if ($condition) {
        echo "ok   {$message}\n";
        return;
    }

    $failures[] = $message;
    echo "FALHA {$message}\n";
}

final class Client
{
    /** @var array<string, string> */
    private array $cookies = [];

    /** @return array{status: int, body: string, location: string} */
    public function request(string $method, string $url, array $data = []): array
    {
        $ch = curl_init($url);
        $headers = [];
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
        $body = substr($raw, $headerSize);
        if (preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;]*)/mi', $header, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->cookies[$match[1]] = $match[2];
            }
        }

        $location = '';
        if (preg_match('/^Location:\s*(\S+)/mi', $header, $match)) {
            $location = $match[1];
        }

        return ['status' => $status, 'body' => $body, 'location' => $location];
    }

    /** @param array<string, string> $data */
    public function follow(string $method, string $url, array $data = []): array
    {
        $response = $this->request($method, $url, $data);
        $guard = 0;
        while ($response['status'] >= 300 && $response['status'] < 400 && $response['location'] !== '' && $guard < 4) {
            $target = $response['location'];
            if (!str_starts_with($target, 'http')) {
                $target = rtrim($url, '/') . '/' . ltrim($target, '/');
                $parts = parse_url($url);
                $target = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '127.0.0.1');
                if (isset($parts['port'])) {
                    $target .= ':' . $parts['port'];
                }
                $target .= $response['location'];
            }
            $response = $this->request('GET', $target);
            $guard++;
        }

        return $response;
    }
}

function csrf(string $html): string
{
    if (preg_match('/name="_csrf" value="([^"]+)"/', $html, $match) !== 1) {
        throw new RuntimeException('Token CSRF não encontrado.');
    }

    return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
}

try {
    $pdo = Database::connection();
} catch (Throwable $exception) {
    fwrite(STDERR, "Banco indisponível: {$exception->getMessage()}\n");
    exit(1);
}

$marketplaces = (int) $pdo->query('SELECT COUNT(*) FROM marketplaces')->fetchColumn();
$offers = (int) $pdo->query('SELECT COUNT(*) FROM ofertas')->fetchColumn();
check($marketplaces === 5, 'catálogo com 5 marketplaces');
check($offers === 0, 'nenhuma oferta gravada antes das pesquisas');

$pdo->prepare('DELETE FROM usuarios WHERE email LIKE :email')->execute(['email' => '%@pricehunter.test']);

$guest = new Client();
$home = $guest->request('GET', $base . '/');
check($home['status'] === 302 && str_contains($home['location'], '/login'), 'painel exige login');

$loginPage = $guest->request('GET', $base . '/login');
check($loginPage['status'] === 200 && str_contains($loginPage['body'], 'Entrar'), 'tela de login');

$noCsrf = $guest->follow('POST', $base . '/cadastro', [
    'nome' => 'Sem Token',
    'email' => 'sem-token@pricehunter.test',
    'senha' => 'senha-forte',
    'senha_confirmacao' => 'senha-forte',
]);
check(str_contains($noCsrf['body'], 'sessão expirou'), 'POST sem CSRF é recusado');

$registerPage = $guest->request('GET', $base . '/cadastro');
$short = $guest->follow('POST', $base . '/cadastro', [
    '_csrf' => csrf($registerPage['body']),
    'nome' => 'Curta',
    'email' => 'curta@pricehunter.test',
    'senha' => '123',
    'senha_confirmacao' => '123',
]);
check(str_contains($short['body'], 'pelo menos 8 caracteres'), 'senha curta é recusada');

$registerPage = $guest->request('GET', $base . '/cadastro');
$longMultibyte = $guest->follow('POST', $base . '/cadastro', [
    '_csrf' => csrf($registerPage['body']),
    'nome' => 'Senha Multibyte',
    'email' => 'senha-multibyte@pricehunter.test',
    'senha' => str_repeat("\xC3\xA1", 37),
    'senha_confirmacao' => str_repeat("\xC3\xA1", 37),
]);
check(str_contains($longMultibyte['body'], 'no máximo 72 bytes'), 'senha acima do limite de bytes é recusada');

$registerPage = $guest->request('GET', $base . '/cadastro');
$owner = $guest->follow('POST', $base . '/cadastro', [
    '_csrf' => csrf($registerPage['body']),
    'nome' => 'Dono Fase Um',
    'email' => 'dono@pricehunter.test',
    'senha' => 'senha-fase-1',
    'senha_confirmacao' => 'senha-fase-1',
]);
check(
    str_contains($owner['body'], 'data-stat="produtos">0<')
    && str_contains($owner['body'], 'data-stat="ofertas">0<')
    && str_contains($owner['body'], 'Mercado Livre')
    && !str_contains($owner['body'], 'senha_hash'),
    'cadastro entra no painel sem ofertas'
);

$guest->follow('POST', $base . '/sair', ['_csrf' => csrf($owner['body'])]);

$registerPage = $guest->request('GET', $base . '/cadastro');
$duplicate = $guest->follow('POST', $base . '/cadastro', [
    '_csrf' => csrf($registerPage['body']),
    'nome' => 'Outro Dono',
    'email' => 'dono@pricehunter.test',
    'senha' => 'senha-fase-1',
    'senha_confirmacao' => 'senha-fase-1',
]);
check(str_contains($duplicate['body'], 'Já existe uma conta'), 'e-mail duplicado é recusado');

$loginPage = $guest->request('GET', $base . '/login');
$injected = $guest->follow('POST', $base . '/login', [
    '_csrf' => csrf($loginPage['body']),
    'email' => "admin' OR '1'='1",
    'senha' => 'nao-importa',
]);
check($injected['status'] === 200 && str_contains($injected['body'], 'e-mail válido'), 'e-mail malformado não chega ao banco');

$loginPage = $guest->request('GET', $base . '/login');
$badLogin = $guest->follow('POST', $base . '/login', [
    '_csrf' => csrf($loginPage['body']),
    'email' => 'nao-existe@pricehunter.test',
    'senha' => 'senha-fase-1',
]);
check($badLogin['status'] === 200 && str_contains($badLogin['body'], 'E-mail ou senha inválidos'), 'login inválido não quebra a consulta');

$loginPage = $guest->request('GET', $base . '/login');
$back = $guest->follow('POST', $base . '/login', [
    '_csrf' => csrf($loginPage['body']),
    'email' => 'dono@pricehunter.test',
    'senha' => 'senha-fase-1',
]);
check(str_contains($back['body'], 'Dono'), 'login com a senha correta');

$profile = $guest->request('GET', $base . '/perfil');
$saved = $guest->follow('POST', $base . '/perfil', [
    '_csrf' => csrf($profile['body']),
    'nome' => 'Dono Atualizado',
    'email' => 'dono@pricehunter.test',
    'telefone' => '11999998888',
    'whatsapp' => '11999998888',
]);
check(str_contains($saved['body'], 'Perfil atualizado') && str_contains($saved['body'], 'Dono Atualizado'), 'perfil é salvo');

$profile = $guest->request('GET', $base . '/perfil');
$badPassword = $guest->follow('POST', $base . '/perfil/senha', [
    '_csrf' => csrf($profile['body']),
    'senha_atual' => 'errada-demais',
    'senha' => 'senha-nova-1',
    'senha_confirmacao' => 'senha-nova-1',
]);
check(str_contains($badPassword['body'], 'senha atual está incorreta'), 'senha atual errada é recusada');

$profile = $guest->request('GET', $base . '/perfil');
$longProfilePassword = str_repeat("\xC3\xA1", 37);
$longProfilePasswordResponse = $guest->follow('POST', $base . '/perfil/senha', [
    '_csrf' => csrf($profile['body']),
    'senha_atual' => 'senha-fase-1',
    'senha' => $longProfilePassword,
    'senha_confirmacao' => $longProfilePassword,
]);
check(str_contains($longProfilePasswordResponse['body'], 'no máximo 72 bytes'), 'nova senha acima do limite de bytes é recusada');

$profile = $guest->request('GET', $base . '/perfil');
$newPassword = $guest->follow('POST', $base . '/perfil/senha', [
    '_csrf' => csrf($profile['body']),
    'senha_atual' => 'senha-fase-1',
    'senha' => 'senha-nova-1',
    'senha_confirmacao' => 'senha-nova-1',
]);
check(str_contains($newPassword['body'], 'Senha alterada'), 'senha é alterada');

$other = new Client();
$registerPage = $other->request('GET', $base . '/cadastro');
$otherHome = $other->follow('POST', $base . '/cadastro', [
    '_csrf' => csrf($registerPage['body']),
    'nome' => 'Visita <script>alert(1)</script>',
    'email' => 'visita@pricehunter.test',
    'senha' => 'senha-fase-1',
    'senha_confirmacao' => 'senha-fase-1',
]);
check(
    str_contains($otherHome['body'], 'data-stat="produtos">0<')
    && str_contains($otherHome['body'], '&lt;script&gt;')
    && !str_contains($otherHome['body'], '<script>alert(1)</script>'),
    'nome com HTML é escapado e não vê dados do outro usuário'
);

$ownerId = (int) $pdo->query("SELECT id FROM usuarios WHERE email = 'dono@pricehunter.test'")->fetchColumn();
$pdo->prepare(
    'INSERT INTO produtos (usuario_id, nome, marca, modelo, categoria, status, frequencia_horas)
     VALUES (:usuario_id, :nome, :marca, :modelo, :categoria, :status, :frequencia_horas)'
)->execute([
    'usuario_id' => $ownerId,
    'nome' => 'Mini GT Ford Mustang',
    'marca' => 'Mini GT',
    'modelo' => 'Ford Mustang',
    'categoria' => 'Miniaturas',
    'status' => 'ativo',
    'frequencia_horas' => 24,
]);

$loggedOut = $guest->follow('POST', $base . '/sair', ['_csrf' => csrf($newPassword['body'])]);
$loginPage = $guest->request('GET', $base . '/login');
$ownerPanel = $guest->follow('POST', $base . '/login', [
    '_csrf' => csrf($loginPage['body']),
    'email' => 'dono@pricehunter.test',
    'senha' => 'senha-nova-1',
]);
check(str_contains($loggedOut['body'], 'Sessão encerrada'), 'logout encerra a sessão');

$otherPanel = $other->request('GET', $base . '/');

check(str_contains($otherPanel['body'], 'data-stat="produtos">0<'), 'segundo usuário continua com zero produtos');
check(str_contains($ownerPanel['body'], 'data-stat="produtos">1<'), 'primeiro usuário vê somente o próprio produto');
check(str_contains($ownerPanel['body'], 'data-stat="ofertas">0<'), 'produto sem pesquisa não ganha oferta');

$asset = $guest->request('GET', $base . '/assets/css/app.css');
check($asset['status'] === 200 && str_contains($asset['body'], '--gold'), 'CSS publicado');

$pdo->prepare('DELETE FROM usuarios WHERE email LIKE :email')->execute(['email' => '%@pricehunter.test']);
$left = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE email LIKE '%@pricehunter.test'")->fetchColumn();
check($left === 0, 'contas de teste removidas');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " verificação(ões) falharam.\n");
    exit(1);
}

echo "Fase 1 verificada.\n";
