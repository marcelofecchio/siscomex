<?php

declare(strict_types=1);

namespace MarceloFecchio\Siscomex\PortalUnico\Resources\Catalogo\Dto;

use MarceloFecchio\Siscomex\PortalUnico\Enum\SituacaoOperadorEstrangeiro;
use MarceloFecchio\Siscomex\PortalUnico\Support\Data;

/** Evento do histórico de alterações de um operador estrangeiro (OperadorEstrangeiroHistoricoResponseDTO). */
final readonly class EventoHistoricoOperadorEstrangeiro
{
    public function __construct(
        public ?\DateTimeImmutable $data,
        public ?string $versao,
        public ?SituacaoOperadorEstrangeiro $situacao,
        public ?string $usuario,
        public ?string $observacao,
    ) {}

    /** @param array<mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            Data::date($a, 'data'),
            Data::stringOrNull($a, 'versao'),
            SituacaoOperadorEstrangeiro::fromTexto(Data::stringOrNull($a, 'situacao')),
            Data::stringOrNull($a, 'usuario'),
            Data::stringOrNull($a, 'observacao'),
        );
    }
}
