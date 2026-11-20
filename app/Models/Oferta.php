<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Oferta
{
    /** @return list<array<string, mixed>> */
    public static function listarDoProduto(int $produtoId, int $usuarioId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT o.id, o.titulo, o.loja, o.vendedor, o.condicao, o.preco, o.frete,
                    o.frete_status, o.desconto, o.preco_total, o.disponibilidade, o.url,
                    o.moeda, o.correspondencia_score, o.atributos_match, o.ultima_vista_em,
                    m.codigo AS marketplace_codigo, m.nome AS marketplace_nome
             FROM ofertas o
             INNER JOIN produtos p
                ON p.id = o.produto_id AND p.usuario_id = o.usuario_id
             INNER JOIN marketplaces m ON m.id = o.marketplace_id
             WHERE p.id = :produto_id
               AND p.usuario_id = :usuario_id
               AND o.produto_id = :produto_filtro
               AND o.usuario_id = :usuario_filtro
               AND o.ativa = 1'
        );
        $stmt->execute([
            'produto_id' => $produtoId,
            'usuario_id' => $usuarioId,
            'produto_filtro' => $produtoId,
            'usuario_filtro' => $usuarioId,
        ]);

        /** @var list<array<string, mixed>> $offers */
        $offers = $stmt->fetchAll();

        return $offers;
    }
}
