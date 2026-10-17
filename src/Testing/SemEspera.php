<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Testing;

use MarceloFecchio\Siscomex\PortalUnico\Support\Sleeper;

/** Não espera de verdade (regra dos 60 s e backoff ficam instantâneos nos testes); registra as esperas. */
final class SemEspera implements Sleeper
{
    /** @var list<int> */
    public array $esperasMs = [];

    public function sleepMs(int $milissegundos): void
    {
        $this->esperasMs[] = $milissegundos;
    }
}
