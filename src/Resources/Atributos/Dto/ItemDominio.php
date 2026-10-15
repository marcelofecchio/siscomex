<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto;

use MFecchio\Siscomex\PortalUnico\Support\Data;

/** Valor de domínio de um atributo (ItemAtributoListaConsultaDTO). */
final readonly class ItemDominio
{
    public function __construct(
        public string $codigo,
        public string $descricao,
        public ?string $filtro = null,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(Data::string($a, 'codigo'), Data::string($a, 'descricao'), Data::stringOrNull($a, 'filtro'));
    }
}
