<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Support;

use MarceloFecchio\Siscomex\PortalUnico\Exceptions\AuthenticationException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\ProductionWriteBlockedException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\RateLimitException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\ServiceUnavailableException;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\SiscomexException;

/**
 * Decide se um erro afeta só o item em processamento ou toda a sequência (lote, vínculos).
 *
 * @internal
 */
final class ClassificacaoErro
{
    /**
     * Rate limit, indisponibilidade, bloqueio de escrita e acesso negado interrompem a sequência.
     * Usa o status HTTP porque o ErrorMapper classifica PUCX-ER0101/0102 como autenticação mesmo
     * num 422 de negócio (no Catálogo esses códigos indicam campo obrigatório/inválido).
     */
    public static function interrompeSequencia(SiscomexException $e): bool
    {
        if ($e instanceof RateLimitException || $e instanceof ServiceUnavailableException || $e instanceof ProductionWriteBlockedException) {
            return true;
        }

        return \in_array($e->httpStatus, [401, 403], true)
            || ($e instanceof AuthenticationException && !\in_array($e->httpStatus, [400, 404, 422], true));
    }
}
