<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto;

use MFecchio\Siscomex\PortalUnico\Support\Data;

/** Objetivo associado a um atributo (ObjetivoAtributoConsultaDTO), ex.: {7, "Produto"}. */
final readonly class ObjetivoVinculado
{
    public function __construct(
        public int $codigo,
        public string $descricao,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(Data::int($a, 'codigo'), Data::string($a, 'descricao'));
    }
}
