<?php

declare(strict_types=1);

namespace App\Integrations;

use Throwable;

final class SearchEngine
{
    private const MINIMUM_MATCH_SCORE = 70;

    /** @var list<MarketplaceDriver> */
    private array $drivers;

    /** @param list<MarketplaceDriver> $drivers */
    public function __construct(array $drivers)
    {
        $this->drivers = [];
        $codes = [];
        foreach ($drivers as $driver) {
            if (!$driver instanceof MarketplaceDriver) {
                throw new \InvalidArgumentException('Todos os drivers precisam implementar MarketplaceDriver.');
            }

            $code = strtolower(trim($driver->codigo()));
            if (preg_match('/^[a-z0-9][a-z0-9_-]{0,39}$/i', $code) !== 1) {
                throw new \InvalidArgumentException('O código do driver é inválido.');
            }
            if (isset($codes[$code])) {
                throw new \InvalidArgumentException('Há mais de um driver registrado para o mesmo marketplace.');
            }

            $codes[$code] = true;
            $this->drivers[] = $driver;
        }
    }

    public function hasDrivers(): bool
    {
        return $this->drivers !== [];
    }

    /**
     * @param array<string, mixed> $product
     * @return array{
     *   status: string,
     *   fontes: list<array<string, mixed>>,
     *   resultados: list<array<string, mixed>>,
     *   erros: list<array{codigo: string, mensagem: string}>
     * }
     */
    public function search(array $product): array
    {
        if (trim((string) ($product['nome'] ?? '')) === '') {
            throw new \InvalidArgumentException('Informe o nome do produto antes de pesquisar.');
        }

        $sources = [];
        $results = [];
        $errors = [];
        $seen = [];
        $availableSources = 0;

        foreach ($this->drivers as $driver) {
            $code = strtolower(trim($driver->codigo()));
            $source = [
                'codigo' => $code,
                'nome' => trim($driver->nome()),
                'status' => 'indisponivel',
                'encontradas' => 0,
                'aceitas' => 0,
                'rejeitadas' => 0,
            ];

            try {
                if (!$driver->disponivel()) {
                    $sources[] = $source;
                    continue;
                }

                $availableSources++;
                $offers = $driver->buscar($product);
                if (!array_is_list($offers)) {
                    throw new \UnexpectedValueException('O driver precisa retornar uma lista de ofertas.');
                }

                $source['status'] = 'concluida';
                foreach ($offers as $index => $rawOffer) {
                    $source['encontradas']++;
                    try {
                        if (!is_array($rawOffer)) {
                            throw new \InvalidArgumentException('A oferta retornada não é um objeto.');
                        }
                        $offer = OfferNormalizer::normalize($rawOffer);
                        $match = ProductOfferMatcher::evaluate($product, $offer);
                        if ($match['score'] < self::MINIMUM_MATCH_SCORE) {
                            $source['rejeitadas']++;
                            continue;
                        }

                        $identity = $offer['id_externo'] ?? $offer['url'];
                        $dedupeKey = $code . "\0" . $identity;
                        if (isset($seen[$dedupeKey])) {
                            $source['rejeitadas']++;
                            continue;
                        }
                        $seen[$dedupeKey] = true;

                        $results[] = $offer + [
                            'codigo_marketplace' => $code,
                            'nome_marketplace' => $source['nome'],
                            'correspondencia_score' => $match['score'],
                            'atributos_match' => $match['atributos'],
                        ];
                        $source['aceitas']++;
                    } catch (\InvalidArgumentException $exception) {
                        $source['status'] = 'parcial';
                        $errors[] = [
                            'codigo' => $code,
                            'mensagem' => 'Oferta ' . ($index + 1) . ': ' . $exception->getMessage(),
                        ];
                    }
                }
            } catch (Throwable $exception) {
                $source['status'] = 'falha';
                $errors[] = [
                    'codigo' => $code,
                    'mensagem' => $exception->getMessage(),
                ];
            }

            $sources[] = $source;
        }

        $status = $errors !== []
            ? ($results === [] ? 'falha' : 'parcial')
            : ($availableSources === 0 ? 'sem_fontes' : 'concluida');

        return [
            'status' => $status,
            'fontes' => $sources,
            'resultados' => $results,
            'erros' => $errors,
        ];
    }
}
