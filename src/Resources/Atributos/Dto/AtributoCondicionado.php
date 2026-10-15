<?php

declare(strict_types=1);

namespace MFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto;

use MFecchio\Siscomex\PortalUnico\Support\Data;

/**
 * Atributo que passa a valer quando a condição sobre o atributo condicionante é satisfeita
 * (ArquivoDiarioCondicionadoDTO).
 */
final readonly class AtributoCondicionado
{
    public function __construct(
        public Atributo $atributo,
        public bool $obrigatorio,
        public bool $multivalorado,
        public ?\DateTimeImmutable $dataInicioVigencia,
        public ?\DateTimeImmutable $dataFimVigencia,
        public ?string $descricaoCondicao,
        public ?CondicaoAtributo $condicao,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        $condicao = Data::arrayOrNull($a, 'condicao');

        return new self(
            atributo: Atributo::fromArray(Data::arrayOrNull($a, 'atributo') ?? []),
            obrigatorio: Data::bool($a, 'obrigatorio'),
            multivalorado: Data::bool($a, 'multivalorado'),
            dataInicioVigencia: Data::date($a, 'dataInicioVigencia'),
            dataFimVigencia: Data::date($a, 'dataFimVigencia'),
            descricaoCondicao: Data::stringOrNull($a, 'descricaoCondicao'),
            condicao: $condicao === null ? null : CondicaoAtributo::fromArray($condicao),
        );
    }
}
