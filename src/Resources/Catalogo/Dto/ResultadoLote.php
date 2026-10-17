<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Enum\StatusItemLote;
use MarceloFecchio\Siscomex\PortalUnico\Exceptions\RateLimitException;

/**
 * Resultado de `produtos()->incluirLote()`, na ordem de entrada.
 *
 * Quando o lote é interrompido (rate limit, portal indisponível, falha de rede, autenticação
 * ou pedido do callback), `interrupcao` diz o motivo e os itens não enviados ficam como
 * Pendente. Para retomar: `incluirLote($cpfCnpjRaiz, $resultado->pendentes())`.
 *
 * @implements \IteratorAggregate<int, ItemLote>
 */
final readonly class ResultadoLote implements \Countable, \IteratorAggregate
{
    /** @param list<ItemLote> $itens */
    public function __construct(
        public array $itens,
        public ?\Throwable $interrupcao = null,
    ) {}

    public function interrompido(): bool
    {
        return $this->porStatus(StatusItemLote::Pendente) !== [] || $this->interrupcao !== null;
    }

    /** @return list<ItemLote> */
    public function porStatus(StatusItemLote $status): array
    {
        return array_values(array_filter($this->itens, static fn(ItemLote $i): bool => $i->status === $status));
    }

    /** @return list<ItemLote> */
    public function incluidos(): array
    {
        return $this->porStatus(StatusItemLote::Incluido);
    }

    /** @return list<ItemLote> */
    public function erros(): array
    {
        return $this->porStatus(StatusItemLote::Erro);
    }

    /**
     * Itens não enviados, com as chaves originais, prontos para um novo incluirLote().
     *
     * @return \Generator<int|string, ProdutoRequest>
     */
    public function pendentes(): \Generator
    {
        foreach ($this->porStatus(StatusItemLote::Pendente) as $item) {
            yield $item->chave => $item->request;
        }
    }

    /** Horário a partir do qual o portal volta a aceitar requisições, se a interrupção foi por PUCX-ER1001. */
    public function retomarApos(): ?\DateTimeImmutable
    {
        return $this->interrupcao instanceof RateLimitException ? $this->interrupcao->liberadoEm : null;
    }

    public function count(): int
    {
        return \count($this->itens);
    }

    /** @return \ArrayIterator<int, ItemLote> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->itens);
    }
}
