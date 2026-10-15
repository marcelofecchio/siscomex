<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Support;

/**
 * Leitura tolerante de valores vindos do JSON do portal (tipagem estrita para o PHPStan).
 *
 * @internal
 */
final class Data
{
    public const string TIMEZONE = 'America/Sao_Paulo';

    /** @param array<mixed> $a */
    public static function string(array $a, string $key, string $default = ''): string
    {
        $v = $a[$key] ?? null;

        return \is_scalar($v) ? (string) $v : $default;
    }

    /** @param array<mixed> $a */
    public static function stringOrNull(array $a, string $key): ?string
    {
        $v = $a[$key] ?? null;

        return \is_scalar($v) && (string) $v !== '' ? (string) $v : null;
    }

    /** @param array<mixed> $a */
    public static function int(array $a, string $key, int $default = 0): int
    {
        $v = $a[$key] ?? null;

        return is_numeric($v) ? (int) $v : $default;
    }

    /** @param array<mixed> $a */
    public static function intOrNull(array $a, string $key): ?int
    {
        $v = $a[$key] ?? null;

        return is_numeric($v) ? (int) $v : null;
    }

    /** @param array<mixed> $a */
    public static function bool(array $a, string $key, bool $default = false): bool
    {
        $v = $a[$key] ?? null;

        return match (true) {
            \is_bool($v) => $v,
            $v === 'true', $v === '1', $v === 1 => true,
            $v === 'false', $v === '0', $v === 0 => false,
            default => $default,
        };
    }

    /**
     * @param array<mixed> $a
     *
     * @return list<array<mixed>>
     */
    public static function listOfArrays(array $a, string $key): array
    {
        $v = $a[$key] ?? null;
        if (!\is_array($v)) {
            return [];
        }

        return array_values(array_filter($v, \is_array(...)));
    }

    /**
     * @param array<mixed> $a
     *
     * @return list<string>
     */
    public static function listOfStrings(array $a, string $key): array
    {
        $v = $a[$key] ?? null;
        if (!\is_array($v)) {
            return [];
        }

        return array_values(array_map(strval(...), array_filter($v, \is_scalar(...))));
    }

    /**
     * @param array<mixed> $a
     *
     * @return array<mixed>|null
     */
    public static function arrayOrNull(array $a, string $key): ?array
    {
        $v = $a[$key] ?? null;

        return \is_array($v) ? $v : null;
    }

    /** @param array<mixed> $a */
    public static function date(array $a, string $key): ?\DateTimeImmutable
    {
        return self::parseDate(self::stringOrNull($a, $key));
    }

    public static function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $tz = new \DateTimeZone(self::TIMEZONE);
        $value = trim($value);

        foreach (['!Y-m-d', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i:s.u', 'Y-m-d H:i:s', '!d/m/Y', 'd/m/Y H:i:s'] as $format) {
            $d = \DateTimeImmutable::createFromFormat($format, $value, $tz);
            if ($d !== false) {
                return $d;
            }
        }

        try {
            return new \DateTimeImmutable($value, $tz);
        } catch (\Exception) {
            return null;
        }
    }

    public static function formatDate(?\DateTimeInterface $date): ?string
    {
        return $date?->format('Y-m-d');
    }

    /**
     * Garante que a resposta é um objeto JSON (array associativo).
     *
     * @return array<mixed>
     */
    public static function object(mixed $value): array
    {
        return \is_array($value) ? $value : [];
    }

    /**
     * Normaliza resposta que pode ser um objeto ou uma lista de objetos.
     *
     * @return list<array<mixed>>
     */
    public static function listFromResponse(mixed $value): array
    {
        if (!\is_array($value) || $value === []) {
            return [];
        }
        if (array_is_list($value)) {
            return array_values(array_filter($value, \is_array(...)));
        }

        return [$value];
    }
}
