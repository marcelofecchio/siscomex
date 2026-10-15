<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Support;

final class SystemSleeper implements Sleeper
{
    public function sleepMs(int $milissegundos): void
    {
        if ($milissegundos > 0) {
            usleep($milissegundos * 1000);
        }
    }
}
