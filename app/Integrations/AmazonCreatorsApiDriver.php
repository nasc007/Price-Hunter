<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Core\Env;
use App\Helpers\Logger;
use JsonException;
use RuntimeException;

final class AmazonCreatorsApiDriver implements MarketplaceDriver
{
    private const API_URL = 'https://creatorsapi.amazon/catalog/v1/searchItems';
    private const MARKETPLACE = 'www.amazon.com.br';

    private const TOKEN_ENDPOINTS = [
        '3.1' => 'https://api.amazon.com/auth/o2/token',
        '3.2' => 'https://api.amazon.co.uk/auth/o2/token',
        '3.3' => 'https://api.amazon.co.jp/auth/o2/token',
    ];

    /** @var array{credential_id: string, credential_secret: string, credential_version: string, partner_tag: string} */
    private array $config;
    private ?string $accessToken = null;
    private int $tokenExpiresAt = 0;

    /**
     * @param array{credential_id?: string, credential_secret?: string, credential_version?: string, partner_tag?: string}|null $config
     */
    public function __construct(?array $config = null)
    {
        $config ??= [
            'credential_id' => (string) Env::get('AMAZON_CREATORS_CREDENTIAL_ID', ''),
            'credential_secret' => (string) Env::get('AMAZON_CREATORS_CREDENTIAL_SECRET', ''),
            'credential_version' => (string) Env::get('AMAZON_CREATORS_CREDENTIAL_VERSION', ''),
            'partner_tag' => (string) Env::get('AMAZON_ASSOCIATES_PARTNER_TAG', ''),
        ];
        $this->config = [
            'credential_id' => trim($config['credential_id'] ?? ''),
            'credential_secret' => trim($config['credential_secret'] ?? ''),
            'credential_version' => trim($config['credential_version'] ?? ''),
            'partner_tag' => trim($config['partner_tag'] ?? ''),
        ];
    }

    public function codigo(): string
    {
        return 'amazon';
    }

    public function nome(): string
    {
        return 'Amazon Brasil';
    }

    public function disponivel(): bool
    {
        return $this->config['credential_id'] !== ''
            && $this->config['credential_secret'] !== ''
            && $this->config['partner_tag'] !== ''
            && isset(self::TOKEN_ENDPOINTS[$this->config['credential_version']]);
    }

    /**
     * @param array<string, mixed> $produto
     * @return list<array<string, mixed>>
     */
    public function buscar(array $produto): array
    {
        if (!$this->disponivel()) {
            throw new RuntimeException(
                'Amazon Creators API está desativada. Configure as credenciais aprovadas e o Partner Tag no .env.'
            );
        }

        $keywords = trim(implode(' ', array_filter([
            (string) ($produto['nome'] ?? ''),
            (string) ($produto['marca'] ?? ''),
            (string) ($produto['modelo'] ?? ''),
            (string) ($produto['ean'] ?? ''),
        ])));
        if ($keywords === '') {
            throw new \InvalidArgumentException('Informe nome, marca, modelo ou EAN para pesquisar na Amazon.');
        }

        $response = $this->requestJson(
            self::API_URL,
            [
                'keywords' => mb_substr($keywords, 0, 120),
                'marketplace' => self::MARKETPLACE,
                'partnerTag' => $this->config['partner_tag'],
                'itemCount' => 10,
                'resources' => [
                    'itemInfo.title',
                    'itemInfo.byLineInfo',
                    'itemInfo.externalIds',
                    'itemInfo.manufactureInfo',
                    'images.primary.medium',
                    'offersV2.listings.price',
                    'offersV2.listings.availability',
                    'offersV2.listings.condition',
                    'offersV2.listings.merchantInfo',
                ],
            ],
            [
                'Authorization: Bearer ' . $this->accessToken(),
                'Content-Type: application/json',
                'Accept: application/json',
                'x-marketplace: ' . self::MARKETPLACE,
            ],
            'SearchItems'
        );

        return self::mapResponse($response, $this->config['partner_tag']);
    }

    /**
     * @param array<string, mixed> $response
     * @return list<array<string, mixed>>
     */
    public static function mapResponse(array $response, string $partnerTag): array
    {
        $items = $response['itemsResult']['items'] ?? null;
        if (!is_array($items) || !array_is_list($items)) {
            throw new RuntimeException('A resposta da Amazon não contém uma lista de produtos.');
        }

        $offers = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $asin = strtoupper(trim((string) ($item['asin'] ?? '')));
            if (preg_match('/^[A-Z0-9]{10}$/', $asin) !== 1) {
                continue;
            }

            $itemInfo = is_array($item['itemInfo'] ?? null) ? $item['itemInfo'] : [];
            $title = self::displayValue($itemInfo['title'] ?? null);
            if ($title === '') {
                continue;
            }

            $brand = self::displayValue($itemInfo['byLineInfo']['brand'] ?? null);
            $model = self::displayValue($itemInfo['manufactureInfo']['model'] ?? null);
            $ean = self::firstDisplayValue($itemInfo['externalIds']['eans']['displayValues'] ?? []);
            if ($ean === '') {
                $ean = self::firstDisplayValue($itemInfo['externalIds']['upcs']['displayValues'] ?? []);
            }
            $image = self::firstImage($item['images']['primary'] ?? null);
            $url = self::productUrl((string) ($item['detailPageURL'] ?? ''), $asin, $partnerTag);
            $listings = $item['offersV2']['listings'] ?? [];
            if (!is_array($listings)) {
                continue;
            }

            foreach ($listings as $index => $listing) {
                if (!is_array($listing)) {
                    continue;
                }

                $price = $listing['price']['money']['amount'] ?? null;
                if (!is_string($price) && !is_int($price) && !is_float($price)) {
                    continue;
                }
                $merchant = trim((string) ($listing['merchantInfo']['name'] ?? ''));
                $sellerKey = $merchant !== '' ? $merchant : (string) $index;
                $offers[] = [
                    'id_externo' => $asin . '-' . substr(hash('sha256', $sellerKey), 0, 16),
                    'titulo' => $title,
                    'url' => $url,
                    'preco' => $price,
                    'frete_status' => 'desconhecido',
                    'desconto' => '0.00',
                    'disponibilidade' => self::availability($listing['availability']['type'] ?? ''),
                    'condicao' => self::condition($listing['condition']['value'] ?? ''),
                    'loja' => $merchant !== '' ? $merchant : 'Amazon',
                    'vendedor' => $merchant,
                    'imagem' => $image,
                    'moeda' => (string) ($listing['price']['money']['currency'] ?? 'BRL'),
                    'ean' => $ean,
                    'marca' => $brand,
                    'modelo' => $model,
                    'atributos' => ['asin' => $asin],
                ];
            }
        }

        return $offers;
    }

    private function accessToken(): string
    {
        if ($this->accessToken !== null && time() < $this->tokenExpiresAt) {
            return $this->accessToken;
        }

        $response = $this->requestJson(
            self::TOKEN_ENDPOINTS[$this->config['credential_version']],
            [
                'grant_type' => 'client_credentials',
                'client_id' => $this->config['credential_id'],
                'client_secret' => $this->config['credential_secret'],
                'scope' => 'creatorsapi::default',
            ],
            ['Content-Type: application/json', 'Accept: application/json'],
            'OAuth'
        );
        $token = $response['access_token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('Amazon OAuth não retornou um access token válido.');
        }

        $expiresIn = filter_var($response['expires_in'] ?? null, FILTER_VALIDATE_INT);
        if (!is_int($expiresIn) || $expiresIn <= 60) {
            throw new RuntimeException('Amazon OAuth retornou um prazo de token inválido.');
        }

        $this->accessToken = $token;
        $this->tokenExpiresAt = time() + $expiresIn - 60;

        return $token;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $headers
     * @return array<string, mixed>
     */
    private function requestJson(string $url, array $payload, array $headers, string $operation): array
    {
        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Não foi possível preparar a solicitação para Amazon.', 0, $exception);
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Não foi possível iniciar a conexão com Amazon Creators API.');
        }

        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ];
        if (!curl_setopt_array($handle, $options)) {
            curl_close($handle);
            throw new RuntimeException('Não foi possível configurar a conexão com Amazon Creators API.');
        }

        $responseBody = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);

        if (!is_string($responseBody)) {
            Logger::warning('Falha na conexão com Amazon Creators API.', [
                'operacao' => $operation,
                'erro' => $curlError,
            ]);
            throw new RuntimeException('Não foi possível conectar à Amazon Creators API. Verifique a conexão e o CA do PHP.');
        }
        if ($status < 200 || $status >= 300) {
            Logger::warning('Amazon Creators API recusou a solicitação.', [
                'operacao' => $operation,
                'http_status' => $status,
            ]);
            throw new RuntimeException(
                'Amazon Creators API retornou HTTP ' . $status . '. Confira a aprovação, as credenciais e os limites da conta.'
            );
        }

        try {
            $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Logger::warning('Amazon Creators API retornou JSON inválido.', [
                'operacao' => $operation,
                'erro' => $exception->getMessage(),
            ]);
            throw new RuntimeException('A resposta da Amazon Creators API estava inválida.', 0, $exception);
        }
        if (!is_array($data)) {
            throw new RuntimeException('Amazon Creators API retornou uma resposta inesperada.');
        }

        return $data;
    }

    private static function displayValue(mixed $value): string
    {
        return is_array($value) && is_string($value['displayValue'] ?? null)
            ? trim($value['displayValue'])
            : '';
    }

    private static function firstDisplayValue(mixed $values): string
    {
        if (!is_array($values)) {
            return '';
        }
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private static function firstImage(mixed $images): string
    {
        if (!is_array($images)) {
            return '';
        }
        foreach (['large', 'medium', 'small'] as $size) {
            $url = $images[$size]['url'] ?? null;
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return '';
    }

    private static function productUrl(string $url, string $asin, string $partnerTag): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return 'https://www.amazon.com.br/dp/' . rawurlencode($asin) . '?tag=' . rawurlencode($partnerTag);
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (
            strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && in_array($host, ['amazon.com.br', 'www.amazon.com.br'], true)
            && !isset($parts['user'])
            && !isset($parts['pass'])
        ) {
            return $url;
        }

        return 'https://www.amazon.com.br/dp/' . rawurlencode($asin) . '?tag=' . rawurlencode($partnerTag);
    }

    private static function availability(mixed $value): string
    {
        return match (strtoupper((string) $value)) {
            'OUT_OF_STOCK', 'UNAVAILABLE' => 'indisponivel',
            'IN_STOCK', 'IN_STOCK_SCARCE' => 'disponivel',
            default => 'sob_consulta',
        };
    }

    private static function condition(mixed $value): string
    {
        return match (strtolower((string) $value)) {
            'new' => 'novo',
            'used' => 'usado',
            'refurbished' => 'recondicionado',
            default => 'desconhecido',
        };
    }
}
