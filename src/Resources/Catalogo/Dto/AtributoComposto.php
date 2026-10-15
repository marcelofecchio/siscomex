<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MFecchio\Siscomex\PortalUnico\Support\Data;

/** Atributo composto: valores dos subatributos (ProdutoCampoCompostoIntegracaoDTO). */
final readonly class AtributoComposto
{
    /** @param list<AtributoValor> $valores */
    public function __construct(
        public string $atributo,
        public array $valores,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(Data::string($a, 'atributo'), array_map(AtributoValor::fromArray(...), Data::listOfArrays($a, 'valores')));
    }

    /** @return array{atributo: string, valores: list<array{atributo: string, valor: string}>} */
    public function toArray(): array
    {
        return [
            'atributo' => $this->atributo,
            'valores' => array_map(static fn(AtributoValor $v): array => $v->toArray(), $this->valores),
        ];
    }
}
