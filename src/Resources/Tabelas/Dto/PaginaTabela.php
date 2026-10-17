<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Tabelas\Dto;

/**
 * Uma página de GET /tabx/api/ext/tabela/{nomeTabela}. `inicio`, `fim` e `total` vêm do header
 * `Content-Range` ("1 - 500/2635"); o portal devolve até 500 registros por página
 * (`Accept-Ranges: dado 500`), o `offset` começa em 1 e o status é **206 enquanto houver mais
 * páginas** e 200 na última (`parcial`). Observado na Validação em 17/10/2026.
 *
 * @implements \IteratorAggregate<int, RegistroTabela>
 */
final readonly class PaginaTabela implements \Countable, \IteratorAggregate
{
    /** @param list<RegistroTabela> $registros */
    public function __construct(
        public string $nomeTabela,
        public array $registros,
        public ?int $inicio = null,
        public ?int $fim = null,
        public ?int $total = null,
        public bool $parcial = false,
    ) {}

    /** Há mais páginas? Pelo Content-Range; sem ele, pelo HTTP 206. */
    public function temMais(): bool
    {
        if ($this->registros === []) {
            return false;
        }
        if ($this->fim !== null && $this->total !== null) {
            return $this->fim < $this->total;
        }

        return $this->parcial;
    }

    /** Offset da próxima página (null se esta é a última). */
    public function proximoOffset(): ?int
    {
        if (!$this->temMais()) {
            return null;
        }

        return $this->fim !== null ? $this->fim + 1 : ($this->inicio ?? 1) + \count($this->registros);
    }

    public function count(): int
    {
        return \count($this->registros);
    }

    /** @return \ArrayIterator<int, RegistroTabela> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->registros);
    }
}
