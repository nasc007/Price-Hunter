<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Painel
{
    /** @return array{produtos: int, lojas: int, ofertas: int, no_alvo: int, ultima_consulta: ?string} */
    public static function resumo(int $usuarioId): array
    {
        $pdo = Database::connection();

        $ultima = $pdo->prepare(
            'SELECT MAX(consultado_em) FROM historico_precos WHERE usuario_id = :usuario_id'
        );
        $ultima->execute(['usuario_id' => $usuarioId]);
        $consulta = $ultima->fetchColumn();

        return [
            'produtos' => self::count(
                'SELECT COUNT(*) FROM produtos WHERE usuario_id = :usuario_id AND status = \'ativo\'',
                $usuarioId
            ),
            'lojas' => self::count(
                'SELECT COUNT(DISTINCT marketplace_id)
                 FROM logs_execucao
                 WHERE usuario_id = :usuario_id AND marketplace_id IS NOT NULL',
                $usuarioId
            ),
            'ofertas' => self::count(
                'SELECT COUNT(*) FROM ofertas WHERE usuario_id = :usuario_id AND ativa = 1',
                $usuarioId
            ),
            'no_alvo' => self::count(
                'SELECT COUNT(DISTINCT produto_id)
                 FROM alertas
                 WHERE usuario_id = :usuario_id AND tipo = \'preco_desejado\' AND status IN (\'novo\', \'lido\')',
                $usuarioId
            ),
            'ultima_consulta' => is_string($consulta) && $consulta !== '' ? $consulta : null,
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function menoresPrecos(int $usuarioId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.nome AS produto, p.preco_desejado, o.loja, o.preco, o.frete, o.desconto,
                    o.preco_total, o.url, o.frete_status, o.disponibilidade
             FROM ofertas o
             INNER JOIN produtos p ON p.id = o.produto_id AND p.usuario_id = o.usuario_id
             INNER JOIN (
                 SELECT produto_id, MIN(preco_total) AS menor
                 FROM ofertas
                 WHERE usuario_id = :usuario_id
                   AND ativa = 1
                   AND preco_total IS NOT NULL
                   AND disponibilidade = \'disponivel\'
                 GROUP BY produto_id
             ) menores ON menores.produto_id = o.produto_id AND o.preco_total = menores.menor
             WHERE o.usuario_id = :usuario_filtro
               AND o.ativa = 1
               AND o.preco_total IS NOT NULL
             ORDER BY o.preco_total ASC, p.nome ASC
             LIMIT 6'
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'usuario_filtro' => $usuarioId,
        ]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public static function alteracoesRecentes(int $usuarioId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT h.consultado_em, h.preco, h.frete, h.preco_total, h.loja, h.disponibilidade,
                    p.nome AS produto
             FROM historico_precos h
             INNER JOIN produtos p ON p.id = h.produto_id AND p.usuario_id = h.usuario_id
             WHERE h.usuario_id = :usuario_id
             ORDER BY h.consultado_em DESC, h.id DESC
             LIMIT 8'
        );
        $stmt->execute(['usuario_id' => $usuarioId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public static function fontes(): array
    {
        $stmt = Database::connection()->query(
            'SELECT codigo, nome, tipo, site_url, ativo, observacao
             FROM marketplaces
             ORDER BY id ASC'
        );

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    private static function count(string $sql, int $usuarioId): int
    {
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['usuario_id' => $usuarioId]);

        return (int) $stmt->fetchColumn();
    }
}
