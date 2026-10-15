<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Support;

/**
 * Abstração de espera, para permitir testes sem sleep real.
 */
interface Sleeper
{
    public function sleepMs(int $milissegundos): void;
}
