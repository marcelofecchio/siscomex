<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Support;

/**
 * Versão de produto ou operador estrangeiro usada nos paths ("1" ou retificação "1.1").
 *
 * @internal
 */
final class Versao
{
    public static function validar(string $versao): string
    {
        $versao = trim($versao);
        if (preg_match('/^\d+(\.\d+)?$/', $versao) !== 1 || \strlen($versao) > 8) {
            throw new \InvalidArgumentException(\sprintf('Versão inválida: "%s". Use "1" ou uma retificação "1.1".', $versao));
        }

        return $versao;
    }

    /** Segmento de path já codificado. */
    public static function path(string $versao): string
    {
        return rawurlencode(self::validar($versao));
    }
}
