<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DateTimeImmutable;
use PDO;
use Throwable;

final class Produto
{
    private const FREQUENCIAS = [1, 6, 12, 24];

    /** @return list<array<string, mixed>> */
    public static function listarDoUsuario(int $usuarioId, string $status = '', string $busca = ''): array
    {
        $sql = 'SELECT id, nome, marca, modelo, categoria, descricao, sku, ean, imagem, url_referencia,
                       palavras_chave, preco_desejado, valor_maximo, status, frequencia_horas,
                       ultima_pesquisa_em, proxima_pesquisa_em, created_at
                FROM produtos
                WHERE usuario_id = :usuario_id';
        $params = ['usuario_id' => $usuarioId];

        if (in_array($status, ['ativo', 'pausado', 'arquivado'], true)) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        if ($busca !== '') {
            $sql .= ' AND (nome LIKE :busca_nome OR marca LIKE :busca_marca
                           OR modelo LIKE :busca_modelo OR categoria LIKE :busca_categoria
                           OR sku LIKE :busca_sku OR ean LIKE :busca_ean)';
            $termo = '%' . $busca . '%';
            $params['busca_nome'] = $termo;
            $params['busca_marca'] = $termo;
            $params['busca_modelo'] = $termo;
            $params['busca_categoria'] = $termo;
            $params['busca_sku'] = $termo;
            $params['busca_ean'] = $termo;
        }

        $sql .= " ORDER BY FIELD(status, 'ativo', 'pausado', 'arquivado'), nome ASC, id DESC";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public static function encontrarDoUsuario(int $id, int $usuarioId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nome, marca, modelo, categoria, descricao, sku, ean, imagem, url_referencia,
                    palavras_chave, preco_desejado, valor_maximo, status, frequencia_horas,
                    ultima_pesquisa_em, proxima_pesquisa_em, created_at
             FROM produtos
             WHERE id = :id AND usuario_id = :usuario_id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'usuario_id' => $usuarioId]);
        $product = $stmt->fetch();

        return is_array($product) ? $product : null;
    }

    /**
     * @param list<array<string, string|null|int>> $produtos
     * @return list<int>
     */
    public static function criarVarios(int $usuarioId, array $produtos): array
    {
        if ($produtos === []) {
            throw new \InvalidArgumentException('Informe ao menos um produto.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $ids = [];
            foreach ($produtos as $produto) {
                $ids[] = self::inserir($pdo, $usuarioId, $produto);
            }
            $pdo->commit();

            return $ids;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, string|null|int> $produto
     */
    public static function atualizarDoUsuario(int $id, int $usuarioId, array $produto): bool
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $lock = $pdo->prepare(
                'SELECT id FROM produtos WHERE id = :id AND usuario_id = :usuario_id FOR UPDATE'
            );
            $lock->execute(['id' => $id, 'usuario_id' => $usuarioId]);
            if (!$lock->fetchColumn()) {
                $pdo->rollBack();
                return false;
            }

            $stmt = $pdo->prepare(
                'UPDATE produtos
                 SET nome = :nome, marca = :marca, modelo = :modelo, categoria = :categoria,
                     descricao = :descricao, sku = :sku, ean = :ean, imagem = :imagem,
                     url_referencia = :url_referencia,
                     palavras_chave = :palavras_chave, preco_desejado = :preco_desejado,
                     valor_maximo = :valor_maximo, status = :status,
                     frequencia_horas = :frequencia_horas,
                     proxima_pesquisa_em = :proxima_pesquisa_em
                 WHERE id = :id AND usuario_id = :usuario_id'
            );
            $stmt->execute(self::params($produto) + [
                'id' => $id,
                'usuario_id' => $usuarioId,
                'proxima_pesquisa_em' => self::proximaExecucao($produto),
            ]);
            self::sincronizarMonitoramento($pdo, $id, $usuarioId, $produto);
            $pdo->commit();

            return true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public static function alterarStatus(int $id, int $usuarioId, string $status): bool
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT frequencia_horas FROM produtos
                 WHERE id = :id AND usuario_id = :usuario_id FOR UPDATE'
            );
            $stmt->execute(['id' => $id, 'usuario_id' => $usuarioId]);
            $frequencia = $stmt->fetchColumn();
            if ($frequencia === false) {
                $pdo->rollBack();
                return false;
            }

            $produto = [
                'frequencia_horas' => (int) $frequencia,
                'status' => $status,
            ];
            $proximaPesquisa = self::proximaExecucao($produto);
            $update = $pdo->prepare(
                'UPDATE produtos
                 SET status = :status, proxima_pesquisa_em = :proxima_pesquisa_em
                 WHERE id = :id AND usuario_id = :usuario_id'
            );
            $update->execute([
                'status' => $status,
                'proxima_pesquisa_em' => $proximaPesquisa,
                'id' => $id,
                'usuario_id' => $usuarioId,
            ]);
            self::sincronizarMonitoramento($pdo, $id, $usuarioId, $produto);
            $pdo->commit();

            return true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public static function excluirDoUsuario(int $id, int $usuarioId): bool
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM produtos WHERE id = :id AND usuario_id = :usuario_id'
        );
        $stmt->execute(['id' => $id, 'usuario_id' => $usuarioId]);

        return $stmt->rowCount() === 1;
    }

    /**
     * @param array<string, string|null|int> $produto
     */
    private static function inserir(PDO $pdo, int $usuarioId, array $produto): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO produtos (
                usuario_id, nome, marca, modelo, categoria, descricao, sku, ean, imagem, url_referencia,
                palavras_chave, preco_desejado, valor_maximo, status, frequencia_horas,
                proxima_pesquisa_em
             ) VALUES (
                :usuario_id, :nome, :marca, :modelo, :categoria, :descricao, :sku, :ean, :imagem,
                :url_referencia,
                :palavras_chave, :preco_desejado, :valor_maximo, :status, :frequencia_horas,
                :proxima_pesquisa_em
             )'
        );
        $stmt->execute(self::params($produto) + [
            'usuario_id' => $usuarioId,
            'proxima_pesquisa_em' => self::proximaExecucao($produto),
        ]);
        $id = (int) $pdo->lastInsertId();
        self::sincronizarMonitoramento($pdo, $id, $usuarioId, $produto);

        return $id;
    }

    /**
     * @param array<string, string|null|int> $produto
     * @return array<string, string|null|int>
     */
    private static function params(array $produto): array
    {
        return [
            'nome' => $produto['nome'],
            'marca' => $produto['marca'],
            'modelo' => $produto['modelo'],
            'categoria' => $produto['categoria'],
            'descricao' => $produto['descricao'],
            'sku' => $produto['sku'],
            'ean' => $produto['ean'],
            'imagem' => $produto['imagem'],
            'url_referencia' => $produto['url_referencia'],
            'palavras_chave' => $produto['palavras_chave'],
            'preco_desejado' => $produto['preco_desejado'],
            'valor_maximo' => $produto['valor_maximo'],
            'status' => $produto['status'],
            'frequencia_horas' => (int) $produto['frequencia_horas'],
        ];
    }

    /**
     * @param array<string, string|null|int> $produto
     */
    private static function sincronizarMonitoramento(PDO $pdo, int $produtoId, int $usuarioId, array $produto): void
    {
        $ativo = $produto['status'] === 'ativo' ? 1 : 0;
        $frequencia = (int) $produto['frequencia_horas'];
        $proxima = self::proximaExecucao($produto);
        $stmt = $pdo->prepare(
            'INSERT INTO monitoramentos (
                produto_id, usuario_id, frequencia_horas, ativo, proxima_execucao_em
             ) VALUES (
                :produto_id, :usuario_id, :frequencia_horas, :ativo, :proxima_execucao_em
             )
             ON DUPLICATE KEY UPDATE
                usuario_id = VALUES(usuario_id),
                frequencia_horas = VALUES(frequencia_horas),
                ativo = VALUES(ativo),
                proxima_execucao_em = VALUES(proxima_execucao_em),
                ultimo_erro = NULL'
        );
        $stmt->execute([
            'produto_id' => $produtoId,
            'usuario_id' => $usuarioId,
            'frequencia_horas' => $frequencia,
            'ativo' => $ativo,
            'proxima_execucao_em' => $proxima,
        ]);
    }

    /**
     * @param array<string, string|null|int> $produto
     */
    private static function proximaExecucao(array $produto): ?string
    {
        if ($produto['status'] !== 'ativo') {
            return null;
        }

        $frequencia = (int) $produto['frequencia_horas'];
        if (!in_array($frequencia, self::FREQUENCIAS, true)) {
            throw new \InvalidArgumentException('Frequência de monitoramento inválida.');
        }

        return (new DateTimeImmutable())->modify('+' . $frequencia . ' hours')->format('Y-m-d H:i:s');
    }
}
