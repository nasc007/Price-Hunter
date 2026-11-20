<?php

declare(strict_types=1);

namespace App\Integrations;

final class PriceComparator
{
    private const MINIMUM_MATCH_SCORE = 70;

    /**
     * @param array<string, mixed> $product
     * @param list<array<string, mixed>> $offers
     * @return array{
     *   gtin_status: string,
     *   menores_por_condicao: array<string, string>,
     *   ofertas_comparaveis: int,
     *   ofertas: list<array<string, mixed>>
     * }
     */
    public static function compare(array $product, array $offers): array
    {
        $gtin = trim((string) ($product['ean'] ?? ''));
        $gtinStatus = $gtin === ''
            ? 'ausente'
            : (GtinValidator::isValid($gtin) ? 'valido' : 'invalido');

        foreach ($offers as &$offer) {
            $score = is_numeric($offer['correspondencia_score'] ?? null)
                ? (float) $offer['correspondencia_score']
                : 0;
            $match = self::matchAttributes($offer['atributos_match'] ?? null);
            $matchByInvalidGtin = $gtinStatus === 'invalido' && ($match['metodo'] ?? '') === 'ean';
            $offer['correspondencia_score'] = $score;
            $offer['identificacao_validada'] = $score >= self::MINIMUM_MATCH_SCORE
                && $score <= 100
                && !$matchByInvalidGtin;
            $offer['identificacao_mensagem'] = $offer['identificacao_validada']
                ? 'Correspondência confirmada'
                : ($matchByInvalidGtin
                    ? 'EAN/GTIN do produto inválido; não use este vínculo'
                    : 'Correspondência insuficiente; confira o produto');

            $financial = self::validateFinancialData($offer);
            $offer['preco_total_confirmado'] = $financial['total'];
            $offer['validacao_preco'] = $financial['message'];
            $condition = (string) ($offer['condicao'] ?? 'desconhecido');
            $offer['validacao_comparacao'] = $financial['message'];
            $offer['comparavel'] = $financial['total'] !== null
                && $offer['identificacao_validada']
                && ($offer['disponibilidade'] ?? '') === 'disponivel'
                && strtoupper((string) ($offer['moeda'] ?? 'BRL')) === 'BRL'
                && in_array($condition, ['novo', 'usado', 'recondicionado'], true);

            if ($financial['total'] !== null && ($offer['disponibilidade'] ?? '') !== 'disponivel') {
                $offer['validacao_comparacao'] = 'Disponibilidade não confirmada';
            } elseif ($financial['total'] !== null && strtoupper((string) ($offer['moeda'] ?? 'BRL')) !== 'BRL') {
                $offer['validacao_comparacao'] = 'Moeda diferente de BRL; não convertida';
            } elseif ($financial['total'] !== null && !in_array($condition, ['novo', 'usado', 'recondicionado'], true)) {
                $offer['validacao_comparacao'] = 'Condição do item não confirmada';
            } elseif ($financial['total'] !== null && !$offer['identificacao_validada']) {
                $offer['validacao_comparacao'] = $offer['identificacao_mensagem'];
            }
        }
        unset($offer);

        usort($offers, static function (array $left, array $right): int {
            if ($left['comparavel'] !== $right['comparavel']) {
                return $left['comparavel'] ? -1 : 1;
            }
            if ($left['comparavel']) {
                $conditionOrder = ['novo' => 0, 'recondicionado' => 1, 'usado' => 2];
                $leftCondition = $conditionOrder[(string) $left['condicao']] ?? 3;
                $rightCondition = $conditionOrder[(string) $right['condicao']] ?? 3;
                if ($leftCondition !== $rightCondition) {
                    return $leftCondition <=> $rightCondition;
                }
                $totalOrder = self::cents((string) $left['preco_total_confirmado'])
                    <=> self::cents((string) $right['preco_total_confirmado']);
                if ($totalOrder !== 0) {
                    return $totalOrder;
                }
            }

            return strcmp(
                (string) ($right['ultima_vista_em'] ?? ''),
                (string) ($left['ultima_vista_em'] ?? '')
            );
        });

        $lowestByCondition = [];
        foreach ($offers as &$offer) {
            $offer['menor_preco'] = false;
            if (!$offer['comparavel']) {
                continue;
            }
            $condition = (string) $offer['condicao'];
            if (!isset($lowestByCondition[$condition])) {
                $lowestByCondition[$condition] = (string) $offer['preco_total_confirmado'];
            }
            $offer['menor_preco'] = self::cents((string) $offer['preco_total_confirmado'])
                === self::cents($lowestByCondition[$condition]);
        }
        unset($offer);

        $comparableCount = count(array_filter(
            $offers,
            static fn (array $offer): bool => $offer['comparavel']
        ));

        return [
            'gtin_status' => $gtinStatus,
            'menores_por_condicao' => $lowestByCondition,
            'ofertas_comparaveis' => $comparableCount,
            'ofertas' => $offers,
        ];
    }

    /**
     * @param array<string, mixed> $offer
     * @return array{total: string|null, message: string}
     */
    private static function validateFinancialData(array $offer): array
    {
        $price = self::centsOrNull($offer['preco'] ?? null);
        $discount = self::centsOrNull($offer['desconto'] ?? '0');
        if ($price === null || $price <= 0 || $discount === null || $discount > $price) {
            return ['total' => null, 'message' => 'Preço ou desconto inválido'];
        }

        $freightStatus = (string) ($offer['frete_status'] ?? '');
        if (!in_array($freightStatus, ['confirmado', 'gratis'], true)) {
            if (($offer['preco_total'] ?? null) !== null) {
                return ['total' => null, 'message' => 'Total informado sem frete confirmado'];
            }

            return [
                'total' => null,
                'message' => $freightStatus === 'indisponivel'
                    ? 'Frete indisponível para comparação'
                    : 'Frete desconhecido; total não comparável',
            ];
        }

        if ($freightStatus === 'gratis') {
            $freight = self::centsOrNull($offer['frete'] ?? null);
            if ($freight !== 0) {
                return ['total' => null, 'message' => 'Frete grátis com valor inconsistente'];
            }
        } else {
            $freight = self::centsOrNull($offer['frete'] ?? null);
            if ($freight === null) {
                return ['total' => null, 'message' => 'Frete confirmado sem valor válido'];
            }
        }

        $totalCents = $price + $freight - $discount;
        if ($totalCents <= 0 || self::centsOrNull($offer['preco_total'] ?? null) !== $totalCents) {
            return ['total' => null, 'message' => 'Total divergente dos valores de preço, frete e desconto'];
        }

        return ['total' => self::formatCents($totalCents), 'message' => 'Preço e frete confirmados'];
    }

    /** @return array<string, mixed> */
    private static function matchAttributes(mixed $attributes): array
    {
        if (is_string($attributes)) {
            $decoded = json_decode($attributes, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($attributes) ? $attributes : [];
    }

    private static function centsOrNull(mixed $value): ?int
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $value = trim((string) $value);
        if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $value) !== 1) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    private static function cents(string $value): int
    {
        return self::centsOrNull($value) ?? 0;
    }

    private static function formatCents(int $value): string
    {
        return intdiv($value, 100) . '.' . str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }
}
