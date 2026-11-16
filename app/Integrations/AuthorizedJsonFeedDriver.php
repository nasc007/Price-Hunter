<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Core\Env;
use JsonException;
use RuntimeException;

final class AuthorizedJsonFeedDriver implements MarketplaceDriver
{
    private const MAX_FEED_BYTES = 10_000_000;

    private string $path;
    private string $sourceCode;
    private string $sourceName;

    public function __construct(?string $path = null, ?string $sourceCode = null, ?string $sourceName = null)
    {
        $configuredPath = trim($path ?? (string) Env::get('AUTHORIZED_JSON_FEED_PATH', ''));
        $this->path = self::resolvePath($configuredPath);
        $configuredCode = trim($sourceCode ?? (string) Env::get('AUTHORIZED_JSON_FEED_CODE', 'feed_autorizado'));
        $configuredName = trim($sourceName ?? (string) Env::get('AUTHORIZED_JSON_FEED_NAME', 'Feed autorizado'));
        $this->sourceCode = $configuredCode !== '' ? $configuredCode : 'feed_autorizado';
        $this->sourceName = $configuredName !== '' ? $configuredName : 'Feed autorizado';
    }

    public function codigo(): string
    {
        return $this->sourceCode;
    }

    public function nome(): string
    {
        return $this->sourceName;
    }

    public function disponivel(): bool
    {
        return $this->path !== '';
    }

    /**
     * @param array<string, mixed> $produto
     * @return list<array<string, mixed>>
     */
    public function buscar(array $produto): array
    {
        if (!$this->disponivel()) {
            throw new RuntimeException('O caminho do feed JSON autorizado não está configurado.');
        }
        if (!is_file($this->path) || !is_readable($this->path)) {
            throw new RuntimeException('O arquivo do feed JSON autorizado não existe ou não pode ser lido.');
        }

        $size = filesize($this->path);
        if ($size === false || $size > self::MAX_FEED_BYTES) {
            throw new RuntimeException('O feed JSON excede o tamanho máximo permitido.');
        }
        $contents = file_get_contents($this->path);
        if ($contents === false) {
            throw new RuntimeException('Não foi possível ler o feed JSON autorizado.');
        }
        if (strlen($contents) > self::MAX_FEED_BYTES) {
            throw new RuntimeException('O feed JSON excede o tamanho máximo permitido.');
        }

        try {
            $offers = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('O feed autorizado contém JSON inválido.', 0, $exception);
        }
        if (!is_array($offers) || !array_is_list($offers)) {
            throw new RuntimeException('O feed autorizado deve conter uma lista JSON de ofertas.');
        }

        foreach ($offers as $offer) {
            if (!is_array($offer)) {
                throw new RuntimeException('Cada item do feed autorizado precisa ser um objeto JSON.');
            }
        }

        return $offers;
    }

    private static function resolvePath(string $path): string
    {
        if ($path === '') {
            return '';
        }
        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|\\\\\\\\|\/)/', $path) === 1) {
            return $path;
        }

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}
