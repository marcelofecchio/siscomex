<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Enum\StatusItemLote;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\SiscomexException;

/** Resultado de um item de `produtos()->incluirLote()`. `chave` é a chave do iterável de entrada. */
final readonly class ItemLote
{
    public function __construct(
        public int|string $chave,
        public ProdutoRequest $request,
        public StatusItemLote $status,
        public ?Produto $produto = null,
        public ?SiscomexException $erro = null,
    ) {}

    /**
     * Mensagens de erro do portal para exibir ao usuário (uma por problema, com o atributo
     * quando o portal o informa).
     *
     * @return list<string>
     */
    public function mensagens(): array
    {
        if ($this->erro === null) {
            return [];
        }
        $mensagens = [];
        foreach ($this->erro->detalhes as $detalhe) {
            $mensagens[] = $detalhe->campo !== null && $detalhe->campo !== '' && !str_contains($detalhe->mensagem, $detalhe->campo)
                ? \sprintf('%s: %s', $detalhe->campo, $detalhe->mensagem)
                : $detalhe->mensagem;
        }

        return $mensagens === [] ? [$this->erro->getMessage()] : $mensagens;
    }
}
