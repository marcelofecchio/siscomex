<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Exceptions;

/**
 * Nova autenticação solicitada antes de completar 60 s desde a última (PLAT-ER2033),
 * detectada localmente pelo SDK ou devolvida pelo portal.
 */
class AuthenticationThrottledException extends AuthenticationException
{
    public ?\DateTimeImmutable $podeAutenticarEm = null;

    public static function local(\DateTimeImmutable $podeAutenticarEm): self
    {
        $e = new self(sprintf(
            'Autenticação recusada pelo SDK: o portal não aceita autenticações em intervalo inferior a 60 s. Tente após %s.',
            $podeAutenticarEm->format('H:i:s'),
        ), codigoErro: 'PLAT-ER2033');
        $e->podeAutenticarEm = $podeAutenticarEm;

        return $e;
    }
}
