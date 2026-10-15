<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Auth\TokenStore;

use MarceloFecchio\Siscomex\PortalUnico\Auth\Session;
use Psr\SimpleCache\CacheInterface;

/**
 * Adaptador para qualquer cache PSR-16 (Redis, Memcached, Symfony Cache, Laravel...).
 *
 * PSR-16 não define lock: informe `$lock` para exclusão mútua entre processos
 * (ex.: fn ($key, $cb) => Cache::lock($key, 30)->block(30, $cb) no Laravel).
 */
final class Psr16TokenStore implements TokenStore
{
    /** @var (\Closure(string, callable): mixed)|null */
    private readonly ?\Closure $lock;

    /**
     * @param (callable(string, callable): mixed)|null $lock
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly string $prefix = 'siscomex_',
        ?callable $lock = null,
    ) {
        $this->lock = $lock === null ? null : $lock(...);
    }

    public function getSession(string $key): ?Session
    {
        $data = $this->cache->get($this->k('session', $key));

        return \is_array($data) ? Session::fromArray($data) : null;
    }

    public function saveSession(string $key, Session $session): void
    {
        $ttl = max(1, $session->csrfExpiresAt->getTimestamp() - time());
        $this->cache->set($this->k('session', $key), $session->toArray(), $ttl);
    }

    public function deleteSession(string $key): void
    {
        $this->cache->delete($this->k('session', $key));
    }

    public function getLastAuthentication(string $key): ?\DateTimeImmutable
    {
        $ts = $this->cache->get($this->k('last', $key));

        return \is_int($ts)
            ? (new \DateTimeImmutable('@'.$ts))->setTimezone(new \DateTimeZone('America/Sao_Paulo'))
            : null;
    }

    public function saveLastAuthentication(string $key, \DateTimeImmutable $at): void
    {
        $this->cache->set($this->k('last', $key), $at->getTimestamp(), 3600);
    }

    public function withLock(string $key, callable $callback): mixed
    {
        if ($this->lock === null) {
            return $callback();
        }

        return ($this->lock)($this->k('lock', $key), $callback);
    }

    /** Chaves PSR-16 não podem conter {}()/\@: — usamos hash. */
    private function k(string $type, string $key): string
    {
        return $this->prefix.$type.'_'.substr(hash('sha256', $key), 0, 32);
    }
}
