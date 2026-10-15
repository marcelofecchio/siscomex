<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Exceptions;

/**
 * Requisição mal formatada (HTTP 400) ou erro(s) de validação da camada de negócio (HTTP 422).
 *
 * A lista de erros individuais (campo `detail` do corpo de erro) fica em `$detalhes`.
 */
class ValidationException extends SiscomexException
{
    /** @return list<string> */
    public function mensagens(): array
    {
        $mensagens = array_map(static fn(ErroDetalhe $d): string => $d->mensagem, $this->detalhes);

        return $mensagens === [] ? [$this->getMessage()] : $mensagens;
    }
}
