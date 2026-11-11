<?php

declare(strict_types=1);

namespace App\Integrations;

/**
 * Contrato comum das fontes de preço.
 * Cada marketplace terá um driver independente nas fases seguintes.
 * Este contrato não consulta lojas e não produz ofertas.
 */
interface MarketplaceDriver
{
    public function codigo(): string;

    public function nome(): string;

    public function disponivel(): bool;

    /**
     * @param array<string, mixed> $produto
     * @return list<array<string, mixed>>
     */
    public function buscar(array $produto): array;
}
