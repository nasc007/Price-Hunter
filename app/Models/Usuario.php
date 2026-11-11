<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Usuario
{
    /** @return array<string, mixed>|null */
    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nome, email, telefone, whatsapp, ativo, ultimo_login_em, created_at, updated_at
             FROM usuarios
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return is_array($user) ? $user : null;
    }

    /** @return array<string, mixed>|null */
    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nome, email, senha_hash, telefone, whatsapp, ativo, ultimo_login_em
             FROM usuarios
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => mb_strtolower(trim($email))]);
        $user = $stmt->fetch();

        return is_array($user) ? $user : null;
    }

    public static function emailEmUso(string $email, int $ignorarId = 0): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT id FROM usuarios WHERE email = :email AND id <> :id LIMIT 1'
        );
        $stmt->execute([
            'email' => mb_strtolower(trim($email)),
            'id' => $ignorarId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public static function criar(string $nome, string $email, string $senha): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO usuarios (nome, email, senha_hash) VALUES (:nome, :email, :senha_hash)'
        );
        $stmt->execute([
            'nome' => $nome,
            'email' => mb_strtolower(trim($email)),
            'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function atualizarPerfil(int $id, string $nome, string $email, ?string $telefone, ?string $whatsapp): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE usuarios
             SET nome = :nome, email = :email, telefone = :telefone, whatsapp = :whatsapp
             WHERE id = :id'
        );
        $stmt->execute([
            'nome' => $nome,
            'email' => mb_strtolower(trim($email)),
            'telefone' => $telefone,
            'whatsapp' => $whatsapp,
            'id' => $id,
        ]);
    }

    public static function atualizarSenha(int $id, string $hash): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE usuarios SET senha_hash = :senha_hash WHERE id = :id'
        );
        $stmt->execute([
            'senha_hash' => $hash,
            'id' => $id,
        ]);
    }

    public static function registrarLogin(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE usuarios SET ultimo_login_em = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public static function senhaConfere(int $id, string $senha): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT senha_hash FROM usuarios WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $hash = $stmt->fetchColumn();

        return is_string($hash) && $hash !== '' && password_verify($senha, $hash);
    }
}
