<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (PHP_VERSION_ID < 80200) {
    fwrite(STDERR, 'Price Hunter requer PHP 8.2 ou superior. Atual: ' . PHP_VERSION . PHP_EOL);
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

$root = dirname(__DIR__);
Env::load($root . '/.env');

$config = Database::config();
$database = $config['database'];
if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
    fwrite(STDERR, "Nome de banco inválido.\n");
    exit(1);
}

$server = Database::serverConnection();
$server->exec(
    'CREATE DATABASE IF NOT EXISTS `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci'
);

$pdo = Database::connection();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        arquivo VARCHAR(190) NOT NULL,
        executado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_schema_migrations_arquivo (arquivo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$files = glob($root . '/database/migrations/*.sql');
if ($files === false) {
    fwrite(STDERR, "Não foi possível listar as migrations.\n");
    exit(1);
}

sort($files, SORT_STRING);

$check = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE arquivo = :arquivo');
$insert = $pdo->prepare('INSERT INTO schema_migrations (arquivo) VALUES (:arquivo)');
$ran = 0;

foreach ($files as $file) {
    $name = basename($file);
    $check->execute(['arquivo' => $name]);
    if ($check->fetchColumn()) {
        echo "ok        {$name} (já aplicada)\n";
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        fwrite(STDERR, "Arquivo vazio: {$name}\n");
        exit(1);
    }

    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
        $stripped = preg_replace('/^\s*--.*$/m', '', $statement) ?? '';
        if (trim($stripped) === '') {
            continue;
        }
        $pdo->exec($stripped);
    }

    $insert->execute(['arquivo' => $name]);
    echo "aplicada  {$name}\n";
    $ran++;
}

echo $ran === 0 ? "Nenhuma migração nova.\n" : "Migrações concluídas: {$ran}\n";
