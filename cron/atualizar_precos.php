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

fwrite(STDOUT, "atualizar_precos: motor de pesquisa ainda não instalado (Fase 3). Nenhuma loja foi consultada e nenhum preço foi gravado." . PHP_EOL);
exit(0);
