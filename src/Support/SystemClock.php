<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Support;

use Psr\Clock\ClockInterface;

final class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo'));
    }
}
