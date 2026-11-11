<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = self::config();
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        self::$pdo = self::connect($dsn, $config);
        self::$pdo->exec("SET time_zone = '-03:00'");

        return self::$pdo;
    }

    public static function serverConnection(): PDO
    {
        $config = self::config();
        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['charset']
        );

        return self::connect($dsn, $config);
    }

    /**
     * @return array{host: string, port: string, database: string, username: string, password: string, charset: string}
     */
    public static function config(): array
    {
        /** @var array{host: string, port: string, database: string, username: string, password: string, charset: string} $config */
        $config = require dirname(__DIR__, 2) . '/config/database.php';

        return $config;
    }

    /**
     * @param array{host: string, port: string, database: string, username: string, password: string, charset: string} $config
     */
    private static function connect(string $dsn, array $config): PDO
    {
        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
