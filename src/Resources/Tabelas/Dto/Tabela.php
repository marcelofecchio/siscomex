<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Tabelas\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Support\Data;

/** Tabela do Tabelas Comex (TabelaApiRepresentation). */
final readonly class Tabela
{
    public function __construct(
        public string $nome,
        public ?string $rotulo = null,
        public ?string $descricao = null,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(Data::string($a, 'nome'), Data::stringOrNull($a, 'rotulo'), Data::stringOrNull($a, 'descricao'));
    }
}
