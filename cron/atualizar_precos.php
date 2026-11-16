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

fwrite(STDOUT, "atualizar_precos: os conectores estão preparados, mas o agendador ainda não está integrado ao motor. Nenhuma loja foi consultada e nenhuma oferta foi gravada." . PHP_EOL);
exit(0);
