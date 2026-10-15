<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Auth\TokenStore;

use MarceloFecchio\Siscomex\PortalUnico\Auth\Session;

/**
 * Armazena a sessão e o horário da última autenticação, permitindo reaproveitar
 * o token entre requisições/processos e respeitar o intervalo mínimo de 60 s.
 */
interface TokenStore
{
    public function getSession(string $key): ?Session;

    public function saveSession(string $key, Session $session): void;

    public function deleteSession(string $key): void;

    public function getLastAuthentication(string $key): ?\DateTimeImmutable;

    public function saveLastAuthentication(string $key, \DateTimeImmutable $at): void;

    /**
     * Executa $callback com exclusão mútua para a chave (evita autenticações concorrentes).
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function withLock(string $key, callable $callback): mixed;
}
