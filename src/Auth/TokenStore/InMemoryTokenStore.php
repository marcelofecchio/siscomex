<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Auth\TokenStore;

use MFecchio\Siscomex\PortalUnico\Auth\Session;

/**
 * Armazenamento em memória (vale apenas durante o processo atual).
 */
final class InMemoryTokenStore implements TokenStore
{
    /** @var array<string, Session> */
    private array $sessions = [];

    /** @var array<string, \DateTimeImmutable> */
    private array $lastAuth = [];

    public function getSession(string $key): ?Session
    {
        return $this->sessions[$key] ?? null;
    }

    public function saveSession(string $key, Session $session): void
    {
        $this->sessions[$key] = $session;
    }

    public function deleteSession(string $key): void
    {
        unset($this->sessions[$key]);
    }

    public function getLastAuthentication(string $key): ?\DateTimeImmutable
    {
        return $this->lastAuth[$key] ?? null;
    }

    public function saveLastAuthentication(string $key, \DateTimeImmutable $at): void
    {
        $this->lastAuth[$key] = $at;
    }

    public function withLock(string $key, callable $callback): mixed
    {
        return $callback();
    }
}
