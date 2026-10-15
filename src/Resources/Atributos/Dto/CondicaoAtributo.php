<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Atributos\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Support\Data;

/**
 * Expressão condicionante (ExpressaoCondicionanteConsultaDTO), encadeável via `composicao`
 * ("||", "&&") e `condicao`.
 */
final readonly class CondicaoAtributo
{
    public function __construct(
        public ?string $operador,
        public ?string $valor,
        public ?string $composicao = null,
        public ?CondicaoAtributo $proxima = null,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        $proxima = Data::arrayOrNull($a, 'condicao');

        return new self(
            Data::stringOrNull($a, 'operador'),
            Data::stringOrNull($a, 'valor'),
            Data::stringOrNull($a, 'composicao'),
            $proxima === null ? null : self::fromArray($proxima),
        );
    }
}
