<?php

declare(strict_types=1);

namespace App\Integrations;

final class ProductOfferMatcher
{
    private const STOP_WORDS = ['com', 'da', 'das', 'de', 'do', 'dos', 'e', 'para'];

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $offer
     * @return array{score: int, atributos: array<string, mixed>}
     */
    public static function evaluate(array $product, array $offer): array
    {
        $productEan = self::digits($product['ean'] ?? '');
        $offerEan = self::digits($offer['ean'] ?? '');
        if ($productEan !== '' && $offerEan !== '') {
            if ($productEan !== $offerEan) {
                return ['score' => 0, 'atributos' => ['metodo' => 'ean', 'divergente' => true]];
            }

            return ['score' => 100, 'atributos' => ['metodo' => 'ean', 'ean' => $productEan]];
        }

        $productSku = mb_strtoupper(trim((string) ($product['sku'] ?? '')));
        $offerSku = mb_strtoupper(trim((string) ($offer['sku'] ?? '')));
        if ($productSku !== '' && $offerSku !== '' && $productSku === $offerSku) {
            return ['score' => 98, 'atributos' => ['metodo' => 'sku', 'sku' => $productSku]];
        }

        $wanted = self::tokens(implode(' ', array_map(
            static fn (string $field): string => (string) ($product[$field] ?? ''),
            ['nome', 'marca', 'modelo']
        )));
        $available = self::tokens(implode(' ', array_map(
            static fn (string $field): string => (string) ($offer[$field] ?? ''),
            ['titulo', 'marca', 'modelo']
        )));
        if (count($wanted) < 2) {
            return ['score' => 0, 'atributos' => ['metodo' => 'texto', 'motivo' => 'termos_insuficientes']];
        }

        $matched = array_values(array_intersect($wanted, $available));
        $score = (int) round(count($matched) / count($wanted) * 100);

        return [
            'score' => $score,
            'atributos' => ['metodo' => 'texto', 'termos_encontrados' => $matched],
        ];
    }

    private static function digits(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';
        return GtinValidator::isValid($digits) ? $digits : '';
    }

    /** @return list<string> */
    private static function tokens(string $value): array
    {
        $value = mb_strtolower($value);
        if (function_exists('iconv')) {
            $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($transliterated !== false) {
                $value = $transliterated;
            }
        }

        if (preg_match_all('/[\p{L}\p{N}]+/u', $value, $matches) === false) {
            return [];
        }
        $tokens = array_values(array_unique(array_filter(
            $matches[0],
            static fn (string $token): bool => mb_strlen($token) > 1 && !in_array($token, self::STOP_WORDS, true)
        )));

        return $tokens;
    }
}
