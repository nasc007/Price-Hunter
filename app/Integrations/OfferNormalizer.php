<?php

declare(strict_types=1);

namespace App\Integrations;

use JsonException;

final class OfferNormalizer
{
    private const CONDITIONS = ['novo', 'usado', 'recondicionado', 'desconhecido'];
    private const FREIGHT_STATUSES = ['confirmado', 'gratis', 'indisponivel', 'desconhecido'];
    private const AVAILABILITY = ['disponivel', 'indisponivel', 'sob_consulta'];

    /**
     * @param array<string, mixed> $offer
     * @return array<string, mixed>
     */
    public static function normalize(array $offer): array
    {
        $title = self::text($offer['titulo'] ?? '', 255);
        if ($title === '') {
            throw new \InvalidArgumentException('A oferta precisa ter um título.');
        }

        $url = self::httpsUrl($offer['url'] ?? '', 'A oferta precisa ter uma URL HTTPS válida.');
        $price = self::decimal($offer['preco'] ?? null, 'preço');
        if (self::cents($price) <= 0) {
            throw new \InvalidArgumentException('O preço da oferta precisa ser maior que zero.');
        }

        $freightStatus = (string) ($offer['frete_status'] ?? 'desconhecido');
        if (!in_array($freightStatus, self::FREIGHT_STATUSES, true)) {
            throw new \InvalidArgumentException('A situação do frete da oferta é inválida.');
        }
        $freight = $offer['frete'] ?? null;
        if ($freightStatus === 'gratis') {
            $freight = '0.00';
        } elseif ($freightStatus === 'indisponivel') {
            $freight = null;
        } elseif ($freightStatus === 'confirmado' && $freight === null) {
            throw new \InvalidArgumentException('Informe o valor do frete confirmado.');
        }
        $freight = $freight === null ? null : self::decimal($freight, 'frete');

        $discount = self::decimal($offer['desconto'] ?? '0', 'desconto');
        if (self::cents($discount) > self::cents($price)) {
            throw new \InvalidArgumentException('O desconto não pode superar o preço do produto.');
        }
        $total = null;
        if (in_array($freightStatus, ['confirmado', 'gratis'], true)) {
            $totalCents = self::cents($price) + self::cents((string) $freight) - self::cents($discount);
            if ($totalCents < 0) {
                throw new \InvalidArgumentException('O desconto não pode superar o preço e o frete.');
            }
            $total = self::formatCents($totalCents);
        }

        $condition = (string) ($offer['condicao'] ?? 'desconhecido');
        if (!in_array($condition, self::CONDITIONS, true)) {
            throw new \InvalidArgumentException('A condição da oferta é inválida.');
        }
        $availability = (string) ($offer['disponibilidade'] ?? 'disponivel');
        if (!in_array($availability, self::AVAILABILITY, true)) {
            throw new \InvalidArgumentException('A disponibilidade da oferta é inválida.');
        }

        $image = null;
        if (isset($offer['imagem']) && $offer['imagem'] !== '') {
            $image = self::httpsUrl($offer['imagem'], 'A imagem da oferta precisa usar HTTPS.', 1000);
        }

        $attributes = $offer['atributos'] ?? [];
        if (!is_array($attributes)) {
            throw new \InvalidArgumentException('Os atributos da oferta precisam ser uma lista ou objeto.');
        }
        try {
            json_encode($attributes, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('Os atributos da oferta não podem ser convertidos para JSON.', 0, $exception);
        }

        $eanInput = self::text($offer['ean'] ?? '', 20);
        $ean = preg_replace('/\D+/', '', $eanInput) ?? '';
        if ($eanInput !== '' && ($ean === '' || !GtinValidator::isValid($ean))) {
            throw new \InvalidArgumentException('O EAN/GTIN retornado pela fonte é inválido.');
        }

        $currency = strtoupper((string) ($offer['moeda'] ?? 'BRL'));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new \InvalidArgumentException('A moeda da oferta precisa ser um código ISO de três letras.');
        }

        return [
            'id_externo' => self::nullableText($offer['id_externo'] ?? null, 120),
            'titulo' => $title,
            'loja' => self::nullableText($offer['loja'] ?? null, 160),
            'vendedor' => self::nullableText($offer['vendedor'] ?? null, 160),
            'condicao' => $condition,
            'preco' => $price,
            'frete' => $freight,
            'frete_status' => $freightStatus,
            'desconto' => $discount,
            'preco_total' => $total,
            'disponibilidade' => $availability,
            'url' => $url,
            'imagem' => $image,
            'moeda' => $currency,
            'ean' => $ean,
            'sku' => self::text($offer['sku'] ?? '', 80),
            'marca' => self::text($offer['marca'] ?? '', 120),
            'modelo' => self::text($offer['modelo'] ?? '', 120),
            'atributos' => $attributes,
        ];
    }

    private static function decimal(mixed $value, string $field): string
    {
        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new \InvalidArgumentException('O ' . $field . ' da oferta é inválido.');
            }
            $value = number_format($value, 2, '.', '');
        }
        if (!is_string($value) && !is_int($value)) {
            throw new \InvalidArgumentException('O ' . $field . ' da oferta é inválido.');
        }

        $value = trim((string) $value);
        if (str_contains($value, ',') && !str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        }
        if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $value) !== 1) {
            throw new \InvalidArgumentException('O ' . $field . ' da oferta deve ter até duas casas decimais.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');
        return ($whole === '' ? '0' : $whole) . '.' . str_pad($fraction, 2, '0');
    }

    private static function cents(string $value): int
    {
        [$whole, $fraction] = explode('.', $value, 2);
        return ((int) $whole * 100) + (int) $fraction;
    }

    private static function formatCents(int $value): string
    {
        return intdiv($value, 100) . '.' . str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function httpsUrl(mixed $value, string $message, int $maxLength = 1000): string
    {
        if (!is_string($value) || strlen($value) > $maxLength || filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException($message);
        }
        $parts = parse_url($value);
        if (
            !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new \InvalidArgumentException($message);
        }

        return $value;
    }

    private static function text(mixed $value, int $maxLength): string
    {
        if (!is_string($value) && !is_int($value)) {
            return '';
        }

        return mb_substr(trim((string) $value), 0, $maxLength);
    }

    private static function nullableText(mixed $value, int $maxLength): ?string
    {
        $text = self::text($value, $maxLength);
        return $text === '' ? null : $text;
    }
}
