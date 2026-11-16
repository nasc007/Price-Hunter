<?php

declare(strict_types=1);

namespace App\Integrations;

/**
 * Contrato comum das fontes de preço.
 * Cada driver converte a resposta da própria fonte para os campos normalizados
 * aceitos pelo motor de pesquisa.
 */
interface MarketplaceDriver
{
    public function codigo(): string;

    public function nome(): string;

    public function disponivel(): bool;

    /**
     * The driver maps its source response to the common offer shape before returning it.
     *
     * @param array<string, mixed> $produto
     * @return list<array{
     *   titulo: string,
     *   url: string,
     *   preco: string|int|float,
     *   id_externo?: string|null,
     *   loja?: string|null,
     *   vendedor?: string|null,
     *   condicao?: string,
     *   frete?: string|int|float|null,
     *   frete_status?: string,
     *   desconto?: string|int|float,
     *   disponibilidade?: string,
     *   imagem?: string|null,
     *   moeda?: string,
     *   ean?: string,
     *   sku?: string,
     *   marca?: string,
     *   modelo?: string,
     *   atributos?: array<string, mixed>
     * }>
     */
    public function buscar(array $produto): array;
}
