<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Http;

/**
 * Requisição de escrita que não foi enviada porque ClientConfig::$simularEscrita está ativo.
 */
final readonly class EscritaSimulada
{
    public function __construct(
        public string $metodo,
        public string $path,
        public string $url,
        public mixed $corpo,
        public \DateTimeImmutable $em,
    ) {}
}
