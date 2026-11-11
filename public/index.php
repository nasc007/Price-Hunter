<?php

declare(strict_types=1);

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    $version = htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Price Hunter</title>';
    echo '<body style="font-family:sans-serif;max-width:42rem;margin:3rem auto;line-height:1.5;padding:0 1rem">';
    echo '<h1>PHP 8.2 necessário</h1>';
    echo '<p>Este servidor está executando PHP ' . $version . '. O Price Hunter exige PHP 8.2 ou superior.</p>';
    echo '<p>O Apache desta máquina carrega o PHP 7.4. Inicie o aplicativo com o PHP 8.2:</p>';
    echo '<pre>C:\\php\\php-8\\php.exe -S 127.0.0.1:8090 -t public public/router.php</pre>';
    echo '<p>Depois abra <a href="http://127.0.0.1:8090">http://127.0.0.1:8090</a>.</p>';
    echo '</body></html>';
    exit;
}

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/app/bootstrap.php';

(new App\Core\App())->run();
