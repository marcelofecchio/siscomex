<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

/**
 * Resultado de GET /ext/operador-estrangeiro. `parcial` = HTTP 206 (o portal truncou o resultado;
 * a API não pagina: refine os filtros ou use a exportação).
 *
 * @implements \IteratorAggregate<int, OperadorEstrangeiro>
 */
final readonly class ResultadoConsultaOperadoresEstrangeiros implements \Countable, \IteratorAggregate
{
    /** @param list<OperadorEstrangeiro> $operadores */
    public function __construct(
        public array $operadores,
        public bool $parcial = false,
    ) {}

    public function count(): int
    {
        return \count($this->operadores);
    }

    /** @return \ArrayIterator<int, OperadorEstrangeiro> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->operadores);
    }

    public function primeiro(): ?OperadorEstrangeiro
    {
        return $this->operadores[0] ?? null;
    }
}
