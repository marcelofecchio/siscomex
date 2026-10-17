<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Enum\StatusItemLote;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\SiscomexException;

/** Resultado de um item de `produtos()->incluirLote()`. `chave` é a chave do iterável de entrada. */
final readonly class ItemLote
{
    /**
     * @param list<Fabricante>       $fabricantes fabricantes pedidos para o item (ProdutoComFabricantes)
     * @param list<ResultadoVinculo> $vinculos    resultado de cada vínculo, quando o produto foi incluído
     */
    public function __construct(
        public int|string $chave,
        public ProdutoRequest $request,
        public StatusItemLote $status,
        public ?Produto $produto = null,
        public ?SiscomexException $erro = null,
        public array $fabricantes = [],
        public array $vinculos = [],
    ) {}

    /** O produto foi incluído (com ou sem todos os vínculos). */
    public function produtoIncluido(): bool
    {
        return \in_array($this->status, [StatusItemLote::Incluido, StatusItemLote::IncluidoSemVinculo], true);
    }

    /** Entrada original, para reenviar um item pendente. */
    public function entrada(): ProdutoRequest|ProdutoComFabricantes
    {
        return $this->fabricantes === [] ? $this->request : new ProdutoComFabricantes($this->request, $this->fabricantes);
    }

    /**
     * Mensagens de erro do portal para exibir ao usuário (uma por problema, com o atributo
     * quando o portal o informa), incluindo as dos vínculos que falharam.
     *
     * @return list<string>
     */
    public function mensagens(): array
    {
        $mensagens = $this->erro === null ? [] : self::mensagensDe($this->erro);
        foreach ($this->vinculos as $vinculo) {
            foreach ($vinculo->erro === null ? [] : self::mensagensDe($vinculo->erro) as $mensagem) {
                $mensagens[] = \sprintf('Fabricante %s: %s', $vinculo->fabricante->codigoOperadorEstrangeiro ?? $vinculo->fabricante->cpfCnpjFabricante ?? $vinculo->fabricante->codigoPais, $mensagem);
            }
        }

        return $mensagens;
    }

    /** @return list<string> */
    private static function mensagensDe(SiscomexException $erro): array
    {
        $mensagens = [];
        foreach ($erro->detalhes as $detalhe) {
            $mensagens[] = $detalhe->campo !== null && $detalhe->campo !== '' && !str_contains($detalhe->mensagem, $detalhe->campo)
                ? \sprintf('%s: %s', $detalhe->campo, $detalhe->mensagem)
                : $detalhe->mensagem;
        }

        return $mensagens === [] ? [$erro->getMessage()] : $mensagens;
    }
}
