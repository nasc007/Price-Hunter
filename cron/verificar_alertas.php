<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (PHP_VERSION_ID < 80200) {
    fwrite(STDERR, 'Price Hunter requer PHP 8.2 ou superior.' . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "verificar_alertas: envio de alertas ainda não instalado (Fase 8). Nenhuma notificação foi disparada." . PHP_EOL);
exit(0);
