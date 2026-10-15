<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Tests\Support;

use MFecchio\Siscomex\PortalUnico\Support\Sleeper;
use Psr\Clock\ClockInterface;

/**
 * Relógio controlável; dormir avança o tempo em vez de esperar.
 */
final class FakeClock implements ClockInterface, Sleeper
{
    /** @var list<int> */
    public array $sleeps = [];

    private \DateTimeImmutable $now;

    public function __construct(string $now = '2026-10-15 10:00:00')
    {
        $this->now = new \DateTimeImmutable($now, new \DateTimeZone('America/Sao_Paulo'));
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify("+{$seconds} seconds");
    }

    public function sleepMs(int $milissegundos): void
    {
        $this->sleeps[] = $milissegundos;
        $this->now = $this->now->modify('+'.($milissegundos * 1000).' microseconds');
    }

    public function totalSleptMs(): int
    {
        return array_sum($this->sleeps);
    }
}
