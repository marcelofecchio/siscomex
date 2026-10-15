<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

/**
 * Resultado de GET /ext/produto. `parcial` = HTTP 206 (o portal truncou o resultado;
 * refine os filtros ou use a exportação).
 *
 * @implements \IteratorAggregate<int, Produto>
 */
final readonly class ResultadoConsultaProdutos implements \Countable, \IteratorAggregate
{
    /** @param list<Produto> $produtos */
    public function __construct(
        public array $produtos,
        public bool $parcial = false,
    ) {}

    public function count(): int
    {
        return \count($this->produtos);
    }

    /** @return \ArrayIterator<int, Produto> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->produtos);
    }

    public function primeiro(): ?Produto
    {
        return $this->produtos[0] ?? null;
    }
}
