<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MFecchio\Siscomex\PortalUnico\Support\Data;

/** Atributo multivalorado (ProdutoCampoMultivaloradoIntegracaoDTO). */
final readonly class AtributoMultivalorado
{
    /** @param list<string> $valores */
    public function __construct(
        public string $atributo,
        public array $valores,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(Data::string($a, 'atributo'), Data::listOfStrings($a, 'valores'));
    }

    /** @return array{atributo: string, valores: list<string>} */
    public function toArray(): array
    {
        return ['atributo' => $this->atributo, 'valores' => $this->valores];
    }
}
