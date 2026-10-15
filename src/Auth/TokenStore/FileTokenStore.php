<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Auth\TokenStore;

use MFecchio\Siscomex\PortalUnico\Auth\Session;

/**
 * Armazenamento em arquivos JSON (permissão 0600) com lock via flock(),
 * compartilhável entre processos da mesma máquina.
 *
 * Mantenha o diretório fora do Git (ex.: storage/), pois contém tokens.
 */
final class FileTokenStore implements TokenStore
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($directory) && !@mkdir($directory, 0o700, true) && !is_dir($directory)) {
            throw new \RuntimeException(\sprintf('Não foi possível criar o diretório de tokens: %s', $directory));
        }
    }

    public function getSession(string $key): ?Session
    {
        $data = $this->read($key);

        return \is_array($data['session'] ?? null) ? Session::fromArray($data['session']) : null;
    }

    public function saveSession(string $key, Session $session): void
    {
        $data = $this->read($key);
        $data['session'] = $session->toArray();
        $this->write($key, $data);
    }

    public function deleteSession(string $key): void
    {
        $data = $this->read($key);
        unset($data['session']);
        $this->write($key, $data);
    }

    public function getLastAuthentication(string $key): ?\DateTimeImmutable
    {
        $ts = $this->read($key)['lastAuthAt'] ?? null;

        return \is_int($ts)
            ? (new \DateTimeImmutable('@'.$ts))->setTimezone(new \DateTimeZone('America/Sao_Paulo'))
            : null;
    }

    public function saveLastAuthentication(string $key, \DateTimeImmutable $at): void
    {
        $data = $this->read($key);
        $data['lastAuthAt'] = $at->getTimestamp();
        $this->write($key, $data);
    }

    public function withLock(string $key, callable $callback): mixed
    {
        $handle = fopen($this->path($key).'.lock', 'c');
        if ($handle === false) {
            return $callback();
        }

        try {
            flock($handle, LOCK_EX);

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function path(string $key): string
    {
        return rtrim($this->directory, '/\\').\DIRECTORY_SEPARATOR.'siscomex-'.hash('sha256', $key).'.json';
    }

    /** @return array<mixed> */
    private function read(string $key): array
    {
        $file = $this->path($key);
        if (!is_file($file)) {
            return [];
        }
        $content = @file_get_contents($file);
        if ($content === false || $content === '') {
            return [];
        }
        $data = json_decode($content, true);

        return \is_array($data) ? $data : [];
    }

    /** @param array<mixed> $data */
    private function write(string $key, array $data): void
    {
        $file = $this->path($key);
        $tmp = $file.'.'.bin2hex(random_bytes(4)).'.tmp';
        file_put_contents($tmp, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX);
        @chmod($tmp, 0o600);
        if (!@rename($tmp, $file)) {
            @unlink($file);
            rename($tmp, $file);
        }
    }
}
