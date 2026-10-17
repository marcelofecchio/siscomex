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

    /**
     * Mensagens agrupadas pelo campo/atributo afetado (ex.: "ATT_1234"); erros sem campo
     * ficam na chave "".
     *
     * @return array<string, list<string>>
     */
    public function mensagensPorCampo(): array
    {
        if ($this->detalhes === []) {
            return ['' => [$this->getMessage()]];
        }
        $porCampo = [];
        foreach ($this->detalhes as $detalhe) {
            $porCampo[$detalhe->campo ?? ''][] = $detalhe->mensagem;
        }

        return $porCampo;
    }
}
