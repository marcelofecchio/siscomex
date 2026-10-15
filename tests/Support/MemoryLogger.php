<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Tests\Support;

use Psr\Log\AbstractLogger;

final class MemoryLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string}> */
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $replace = [];
        foreach ($context as $key => $value) {
            $replace['{'.$key.'}'] = \is_scalar($value) ? (string) $value : json_encode($value);
        }
        $this->records[] = [
            'level' => \is_string($level) ? $level : 'unknown',
            'message' => strtr((string) $message, $replace),
        ];
    }

    public function allText(): string
    {
        return implode("\n", array_map(static fn(array $r): string => $r['level'].': '.$r['message'], $this->records));
    }
}
