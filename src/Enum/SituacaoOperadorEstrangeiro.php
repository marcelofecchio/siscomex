<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Enum;

/** Situação de uma versão de operador estrangeiro (OperadorEstrangeiroIntegracaoDTO.situacao). */
enum SituacaoOperadorEstrangeiro: string
{
    case Ativado = 'ATIVADO';
    case Desativado = 'DESATIVADO';

    /** Aceita a grafia da spec ("ATIVADO") e a observada nas consultas ("Desativado"). */
    public static function fromTexto(?string $valor): ?self
    {
        return $valor === null ? null : self::tryFrom(strtoupper(trim($valor)));
    }
}
